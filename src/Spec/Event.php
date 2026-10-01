<?php

declare(strict_types=1);

namespace App\Spec;

use App\Scene\LowEndBias;
use App\Scene\RoomBounds;
use App\Scene\StackOrientation;
use Symfony\Component\Yaml\Yaml;

/**
 * One event: its room, how each system is set up, and what each system brings.
 *
 * **What a system brings lives here, as `systems.<owner>.brings`.** It used to be a roster file of its own in
 * `rosters/`, one per event and system, and every one of them was about exactly one event already. Two files for one
 * occasion meant two places to look and two ids to keep in step, so 0.130.0 folded the counts into the event.
 *
 * **A spec says how many exist and `brings` says how many turn up, and the two are different facts.** Innschleife
 * owns four small tops and brings either those four or the two big ones, a cabinet sits in the workshop with a blown
 * driver, a system lends half a rig out for the weekend. None of that corrects the spec, which the catalog, the load
 * plan and every weight total want exactly as it is. So the difference is written down beside the specs rather than
 * inside them, and it is written down at all rather than typed into a shell, because "what they are bringing on the
 * 6th" is a fact somebody told us and facts belong in the repository.
 *
 * **Zero is how a cabinet stays at home.** It is a count like any other and every consumer already drops a device
 * with no units, so `tms2: 0` names the cabinet it excludes, where leaving the line out means "bring what the spec
 * says". A device the map never mentions keeps its spec's quantity, which is what lets one system's counts leave
 * every other system's gear untouched.
 *
 * **The room is optional**, because a past event is worth recording for what was brought even when nobody measured
 * the hall. No room means no limit.
 */
final class Event
{
    /**
     * @param array<string, array{interface_height_m: float, target_sub_height_m: float}> $systems
     * @param array<string, StackOrientation> $orientations how each named system is set up at this event
     * @param list<string> $standing device ids that stand as measured whatever their system's orientation
     * @param array<string, array<string, int>> $brings owner to device id to units brought, in file order
     * @param array<string, LowEndBias> $lowEnds where each named system wants its lowest cabinets
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
        public readonly array $brings = [],
        public readonly array $lowEnds = [],
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $reader = new ArrayReader($data);
        $room = $reader->optionalSection('room');
        $systems = [];
        $brings = [];
        $orientations = [];
        $standing = [];
        $lowEnds = [];
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
            if ($system->has('low_end')) {
                /** @var LowEndBias $lowEnd */
                $lowEnd = $system->requireEnum('low_end', LowEndBias::class);
                $lowEnds[$owner] = $lowEnd;
            }
            foreach ($system->stringList('stand') as $id) {
                $standing[] = $id;
            }
            if ($system->has('brings')) {
                $brings[$owner] = self::counts($system->requireSection('brings'), 'systems.'.$owner.'.brings');
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
            null === $room ? new RoomBounds() : new RoomBounds($room->requireFloat('width_m'), $room->requireFloat('height_m')),
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
            $brings,
            $lowEnds,
        );
    }

    /**
     * Whole, non-negative counts by device id. An empty map is refused, because a `brings` that changes no count says
     * nothing and was almost certainly meant to say something.
     *
     * @return array<string, int>
     */
    private static function counts(ArrayReader $brings, string $path): array
    {
        $counts = [];
        foreach ($brings->keys() as $id) {
            $count = $brings->requireInt($id);
            if ($count < 0) {
                throw new InvalidSpecException(sprintf('%s.%s is %d, and a count cannot be negative', $path, $id, $count));
            }
            $counts[$id] = $count;
        }
        if ([] === $counts) {
            throw new InvalidSpecException($path.' is empty, and a brings that changes no count says nothing');
        }

        return $counts;
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
