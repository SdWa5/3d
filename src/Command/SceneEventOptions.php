<?php

declare(strict_types=1);

namespace App\Command;

use App\Scene\RoomBounds;
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
     */
    private function __construct(
        public readonly RoomBounds $room,
        public readonly array $interfaces,
        public readonly array $targets,
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
        $known = array_unique(array_map(static fn (DeviceSpec $device): string => $device->owner, $devices));
        foreach (array_unique([...array_keys($interfaces), ...array_keys($targets)]) as $owner) {
            if (!in_array($owner, $known, true)) {
                throw new InvalidSpecException('unknown system owner '.$owner);
            }
            if (isset($interfaces[$owner], $targets[$owner]) && $targets[$owner] < $interfaces[$owner]) {
                throw new InvalidSpecException('system target must be at or above its interface');
            }
        }
        $input->setOption('room-width', null === $width ? null : (string) $width);
        $input->setOption('room-height', null === $height ? null : (string) $height);

        return new self($room, $interfaces, $targets);
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
