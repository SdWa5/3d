<?php

declare(strict_types=1);

namespace App\Command;

use App\Scene\RoomBounds;
use App\Scene\StackOrientation;
use App\Spec\DeviceSpec;
use App\Spec\Event;
use App\Spec\InvalidSpecException;
use Symfony\Component\Console\Input\InputInterface;

/** Resolves editable event settings into numeric options that recorded commands can replay unchanged. */
final class SceneEventOptions
{
    /**
     * @param array<string, float> $interfaces
     * @param array<string, float> $targets
     * @param array<string, StackOrientation> $orientations how each named system is set up, see {@see rolls}
     * @param list<string> $standing device ids that stand as measured under any orientation
     */
    private function __construct(
        public readonly RoomBounds $room,
        public readonly array $interfaces,
        public readonly array $targets,
        /** `TRUSS:SEGMENTS:TOWER`, read only once a deco device is brought, see {@see StackBackdrop::parse}. */
        public readonly ?string $backdrop = null,
        public readonly array $orientations = [],
        public readonly array $standing = [],
    ) {
    }

    /** @param array<string, DeviceSpec> $devices */
    public static function resolve(InputInterface $input, string $directory, array $devices): self
    {
        $id = $input->getOption('event');
        $event = null === $id ? null : Event::load($directory, (string) $id);
        $width = self::number($input->getOption('room-width'), 'room-width');
        $height = self::number($input->getOption('room-height'), 'room-height');
        // An explicit option may tighten an event's hard limit, but cannot loosen it.
        if (null !== $event) {
            $width = null === $width ? $event->room->widthM : min($width, $event->room->widthM);
            $height = null === $height ? $event->room->heightM : min($height, $event->room->heightM);
        }
        $room = new RoomBounds($width, $height);
        $interfaces = [];
        $targets = [];
        $systems = null === $event ? [] : $event->systems;
        foreach ($systems as $owner => $settings) {
            $interfaces[$owner] = $settings['interface_height_m'];
            $targets[$owner] = $settings['target_sub_height_m'];
        }
        foreach (['system-interface' => &$interfaces, 'system-target' => &$targets] as $option => &$values) {
            foreach ((array) $input->getOption($option) as $pair) {
                $parts = explode(':', (string) $pair);
                if (2 !== count($parts) || '' === $parts[0]) {
                    throw new InvalidSpecException('--'.$option.' expects OWNER:METRES');
                }
                $value = self::number($parts[1], $option);
                if (null === $value) {
                    throw new InvalidSpecException('--'.$option.' needs a value');
                }
                $values[$parts[0]] = $value;
            }
            ksort($values);
            $input->setOption($option, array_map(
                static fn (string $owner): string => $owner.':'.$values[$owner],
                array_keys($values),
            ));
        }
        unset($values);

        // **THE SAME SHAPE AS THE INTERFACE, AND RECORDED THE SAME WAY.** The event states how each system is set up,
        // `--system-orientation=OWNER:MODE` states or overrides it, and the resolved pairs are written back so the
        // recorded line replays them without the event file.
        $orientations = null === $event ? [] : $event->orientations;
        foreach ((array) $input->getOption('system-orientation') as $pair) {
            $parts = explode(':', (string) $pair);
            $mode = 2 === count($parts) && '' !== $parts[0] ? StackOrientation::tryFrom($parts[1]) : null;
            if (null === $mode) {
                throw new InvalidSpecException('--system-orientation expects OWNER:upright|turned|mixed');
            }
            $orientations[$parts[0]] = $mode;
        }
        ksort($orientations);
        $input->setOption('system-orientation', array_map(
            static fn (string $owner): string => $owner.':'.$orientations[$owner]->value,
            array_keys($orientations),
        ));
        $standing = array_values(array_unique([
            ...(null === $event ? [] : $event->standing),
            ...array_map('strval', (array) $input->getOption('stand')),
        ]));
        sort($standing);
        $input->setOption('stand', $standing);
        foreach ($standing as $id) {
            if (!isset($devices[$id])) {
                throw new InvalidSpecException('--stand names unknown device '.$id);
            }
        }

        $known = array_unique(array_map(static fn (DeviceSpec $device): string => $device->owner, $devices));
        foreach (array_unique([...array_keys($interfaces), ...array_keys($targets), ...array_keys($orientations)]) as $owner) {
            if (!in_array($owner, $known, true)) {
                throw new InvalidSpecException('unknown system owner '.$owner);
            }
            if (isset($interfaces[$owner], $targets[$owner]) && $targets[$owner] < $interfaces[$owner]) {
                throw new InvalidSpecException('system target must be at or above its interface');
            }
        }
        // The event's air between stacks replaces the default, and an explicit `--clearance` replaces the event's.
        // Only the number is recorded, as for the room.
        if (null !== $event?->clearanceM && !$input->hasParameterOption('--clearance')) {
            $input->setOption('clearance', (string) $event->clearanceM);
        }
        $input->setOption('room-width', null === $width ? null : (string) $width);
        $input->setOption('room-height', null === $height ? null : (string) $height);

        // A stated backdrop replaces the event's rather than narrowing it, because two trusses have no minimum.
        $backdrop = $input->getOption('backdrop') ?? $event?->backdrop;
        $input->setOption('backdrop', $backdrop);

        return new self($room, $interfaces, $targets, null === $backdrop ? null : (string) $backdrop, $orientations, $standing);
    }

    /**
     * The device ids out of `$ids` that lie on their sides, **resolved per system** and nowhere else.
     *
     * A cabinet whose owner has a stated orientation follows it, and every other cabinet follows the sweep's
     * `$orientation`. A null mode on both sides means the caller named the cabinets outright with `--roll-mirror`, which
     * `$stated` carries. A standing cabinet is never rolled by a mode, because the owner of the gear said so: at the
     * next event Innschleife is set up turned, and its kickers stand as measured anyway.
     *
     * @param array<string, DeviceSpec> $devices
     * @param list<string> $ids
     * @param list<string> $stated
     *
     * @return list<string>
     */
    public function rolls(?StackOrientation $orientation, array $devices, array $ids, array $stated): array
    {
        $rolls = [];
        foreach ($ids as $id) {
            $mode = $this->orientations[$devices[$id]->owner ?? ''] ?? $orientation;
            if (null === $mode) {
                if (in_array($id, $stated, true)) {
                    $rolls[] = $id;
                }
                continue;
            }
            if (!in_array($id, $this->standing, true) && [] !== $mode->rolls($devices, [$id])) {
                $rolls[] = $id;
            }
        }

        return $rolls;
    }

    /**
     * Whether every cabinet of the rig belongs to a system with a stated orientation, which leaves the sweep's
     * orientation axis nothing to vary.
     *
     * @param array<string, DeviceSpec> $devices
     * @param list<string> $ids
     */
    public function fixesOrientation(array $devices, array $ids): bool
    {
        if ([] === $this->orientations || [] === $ids) {
            return false;
        }
        foreach ($ids as $id) {
            if (!isset($devices[$id], $this->orientations[$devices[$id]->owner])) {
                return false;
            }
        }

        return true;
    }

    /**
     * A system preference follows its subs even when it carries borrowed tops. A pooled sub wall keeps the defaults.
     *
     * @param list<string> $ids
     * @param array<string, DeviceSpec> $devices
     */
    public function subOwner(array $ids, array $devices): ?string
    {
        $owners = [];
        foreach ($ids as $id) {
            if ('sub' === $devices[$id]->subtype) {
                $owners[$devices[$id]->owner] = true;
            }
        }

        return 1 === count($owners) ? array_key_first($owners) : null;
    }

    private static function number(mixed $value, string $option): ?float
    {
        if (null === $value) {
            return null;
        }
        if (!is_numeric($value) || !is_finite((float) $value) || (float) $value <= 0.0) {
            throw new InvalidSpecException('--'.$option.' must be a finite positive number');
        }

        return (float) $value;
    }
}
