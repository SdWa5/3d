<?php

declare(strict_types=1);

namespace App\Spec;

use App\Scene\RoomBounds;
use App\Scene\StackOrientation;
use Symfony\Component\Yaml\Yaml;

/** An event states its room and system preferences. Rosters continue to state counts alone. */
final class Event
{
    /**
     * @param array<string, array{interface_height_m: float, target_sub_height_m: float}> $systems
     * @param array<string, StackOrientation> $orientations how each named system is set up at this event
     * @param list<string> $standing device ids that stand as measured whatever their system's orientation
     */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly RoomBounds $room,
        public readonly array $systems = [],
        /** `TRUSS:SEGMENTS:TOWER`, the truss a `deco` device brought to this event hangs from, or null for none. */
        public readonly ?string $backdrop = null,
        public readonly array $orientations = [],
        public readonly array $standing = [],
        /** Air between neighbouring stacks at this event, or null for the command's default. */
        public readonly ?float $clearanceM = null,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $reader = new ArrayReader($data);
        $room = $reader->requireSection('room');
        $systems = [];
        $orientations = [];
        $standing = [];
        $settings = $reader->optionalSection('systems');
        foreach ($settings?->keys() ?? [] as $owner) {
            $system = $settings->requireSection($owner);
            // The interface and the target come as a pair or not at all, because a target without its interface
            // has nothing to stand above.
            if ($system->has('interface_height_m') || $system->has('target_sub_height_m')) {
                $interface = $system->requireFloat('interface_height_m');
                $target = $system->requireFloat('target_sub_height_m');
                if (!is_finite($interface) || !is_finite($target) || $interface <= 0.0 || $target < $interface) {
                    throw new InvalidSpecException('systems.'.$owner.' needs a positive interface and a finite target at or above it');
                }
                $systems[$owner] = ['interface_height_m' => $interface, 'target_sub_height_m' => $target];
            }
            if ($system->has('orientation')) {
                /** @var StackOrientation $orientation */
                $orientation = $system->requireEnum('orientation', StackOrientation::class);
                $orientations[$owner] = $orientation;
            }
            foreach ($system->stringList('stand') as $id) {
                $standing[] = $id;
            }
        }

        $backdrop = $reader->optionalSection('backdrop');
        $clearance = $reader->optionalFloat('stack_clearance_m');
        if (null !== $clearance && (!is_finite($clearance) || $clearance < 0.0)) {
            throw new InvalidSpecException('stack_clearance_m must be a finite distance of zero or more');
        }

        return new self(
            $reader->requireString('id'),
            $reader->requireString('name'),
            new RoomBounds($room->requireFloat('width_m'), $room->requireFloat('height_m')),
            $systems,
            null === $backdrop ? null : sprintf(
                '%s:%d:%s',
                $backdrop->requireString('truss'),
                $backdrop->requireInt('segments'),
                $backdrop->requireString('towers'),
            ),
            $orientations,
            $standing,
            $clearance,
        );
    }

    /** @throws InvalidSpecException */
    public static function load(string $directory, string $id): self
    {
        if (1 !== preg_match('/^[a-z0-9][a-z0-9-]*$/', $id) || !is_file($directory.'/'.$id.'.yaml')) {
            throw new InvalidSpecException('no event named '.$id);
        }
        try {
            $data = Yaml::parseFile($directory.'/'.$id.'.yaml');
        } catch (\RuntimeException $e) {
            throw new InvalidSpecException('cannot read event '.$id.': '.$e->getMessage(), 0, $e);
        }
        if (!is_array($data) || array_is_list($data)) {
            throw new InvalidSpecException('expected an event mapping');
        }
        /** @var array<string, mixed> $data */
        $event = self::fromArray($data);
        if ($event->id !== $id) {
            throw new InvalidSpecException('event id must match its file name');
        }

        return $event;
    }
}
