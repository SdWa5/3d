<?php

declare(strict_types=1);

namespace App\Scene;

use App\Spec\DeviceSpec;

/**
 * What a cabinet measures once it is rolled, and **where its body sits relative to its own origin**.
 *
 * Both halves matter, and the second one is the surprise. Geometry runs from the cabinet's bottom-centre, so
 * rolling a quarter turn does not merely swap width and height — it moves the whole body off to one side of the
 * origin it was measured from: entirely to the **right at 90**, entirely to the **left at 270**. A Flexy is
 * 591 × 763 upright and 763 × 591 on its side, and its body spans `x ∈ [0, 0.763]` at roll 90 rather than
 * `[−0.382, +0.382]`. Anything that lays out rolled cabinets by spacing their origins therefore leaves a hole on
 * one side and an overlap on the other.
 *
 * The numbers come from {@see GroupStack::cabinetBox}, which is {@see PlacedDevice::worldBox} on a cabinet with
 * nothing but the roll applied — **the compiler's own computation**. Swapping width and height by hand would be
 * near enough for our subs, which are plain boxes, and wrong for a Tecnare: the shell is a trapezoid with a
 * chamfer, so its rolled width comes from its *front* height on one edge and its full height on the other. A
 * solver that measures a cabinet differently from the way it is placed is the one bug a render cannot show.
 *
 * Memoised because the fill search asks for the same handful of boxes hundreds of times, and each answer costs
 * eight corner rotations.
 */
final class RolledBox
{
    /** @var array<string, array{min: array{float, float, float}, max: array{float, float, float}}> */
    private static array $cache = [];

    /**
     * @return array{min: array{float, float, float}, max: array{float, float, float}}
     */
    public static function of(DeviceSpec $device, float $rollDeg): array
    {
        $key = $device->id.'|'.$rollDeg;

        return self::$cache[$key] ??= GroupStack::cabinetBox($device, 0.0, $rollDeg);
    }

    /** How wide it stands once rolled — the Flexy's 763 mm rather than its 591. */
    public static function widthOf(DeviceSpec $device, float $rollDeg): float
    {
        $box = self::of($device, $rollDeg);

        return $box['max'][0] - $box['min'][0];
    }

    /** How tall it stands once rolled, which is what the tier above it rests at. */
    public static function heightOf(DeviceSpec $device, float $rollDeg): float
    {
        $box = self::of($device, $rollDeg);

        return $box['max'][2] - $box['min'][2];
    }

    /**
     * Where to put the cabinet's origin so its **body** lands centred on `$centreX`.
     *
     * Upright this is `$centreX` and nothing to think about. Rolled it is offset by half the body's width,
     * towards the side the body did *not* fall to — which is the whole reason a rolled row cannot be laid out by
     * stepping origins uniformly.
     */
    public static function originFor(DeviceSpec $device, float $rollDeg, float $centreX): float
    {
        $box = self::of($device, $rollDeg);

        return $centreX - ($box['min'][0] + $box['max'][0]) / 2;
    }
}
