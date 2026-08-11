<?php

declare(strict_types=1);

namespace App\Tests\Scene;

use App\Scene\LayoutMode;
use App\Scene\StackEntry;
use App\Spec\ArrayReader;
use App\Spec\InvalidSpecException;
use PHPUnit\Framework\TestCase;

/**
 * One entry of a `stack`'s `from` list, in either of its two forms.
 *
 * The shorthand mattering as much as the mapping is the point: a stack lists five devices and at most one of
 * them usually needs an option, so making every entry a mapping would be ceremony on four lines out of five —
 * and it would have broken every scene and every generated file that already exists.
 */
final class StackEntryTest extends TestCase
{
    public function testABareDeviceIdIsTheShorthandForAnEntryWithNoOptions(): void
    {
        $entry = StackEntry::fromValue('flexy-folded-horn-hybrid');

        self::assertSame('flexy-folded-horn-hybrid', $entry->device);
        self::assertNull($entry->count, 'null means "however many the spec says we own"');
        self::assertNull($entry->align);
        self::assertSame([], $entry->mixWith);
        self::assertSame([], $entry->problems());
    }

    public function testTheMappingFormReadsEveryOption(): void
    {
        $entry = StackEntry::fromValue(new ArrayReader([
            'device' => 'achenbach-18',
            'count' => 6,
            'align' => 'block',
            'mix_with' => 'skram',
        ]));

        self::assertSame('achenbach-18', $entry->device);
        self::assertSame(6, $entry->count);
        self::assertSame(LayoutMode::Block, $entry->align);
        self::assertSame(['skram'], $entry->mixWith);
    }

    /** `mix_with: skram` and `mix_with: [skram, other]` are the same field, one item or several. */
    public function testMixWithTakesOneNameOrAList(): void
    {
        $one = StackEntry::fromValue(new ArrayReader(['device' => 'a', 'mix_with' => 'b']));
        $many = StackEntry::fromValue(new ArrayReader(['device' => 'a', 'mix_with' => ['b', 'c']]));

        self::assertSame(['b'], $one->mixWith);
        self::assertSame(['b', 'c'], $many->mixWith);
    }

    /**
     * A `count` of zero or less is a typo, not a way of leaving a device out — leaving it out is done by not
     * listing it. Left unchecked it would produce a tier of nothing.
     */
    public function testACountBelowOneIsRejected(): void
    {
        $entry = StackEntry::fromValue(new ArrayReader(['device' => 'achenbach-18', 'count' => 0]));

        self::assertSame(
            ["stack.from 'achenbach-18': count must be at least 1, got 0"],
            $entry->problems(),
        );
    }

    public function testMixingWithItselfIsRejected(): void
    {
        $entry = StackEntry::fromValue(new ArrayReader(['device' => 'skram', 'mix_with' => 'skram']));

        self::assertSame(["stack.from 'skram': mix_with names itself"], $entry->problems());
    }

    /**
     * Unknown keys are refused for the same reason `arc` and `lattice` refuse them: every field here changes
     * the rig, and `counts:` for `count:` would silently place what the inventory holds instead.
     */
    public function testReadingRejectsAnUnknownKey(): void
    {
        $this->expectException(InvalidSpecException::class);
        $this->expectExceptionMessage("stack.from: unknown key 'counts'");

        StackEntry::fromValue(new ArrayReader(['device' => 'achenbach-18', 'counts' => 6]));
    }

    public function testReadingRejectsAnEntryWithNoDevice(): void
    {
        $this->expectException(InvalidSpecException::class);

        StackEntry::fromValue(new ArrayReader(['count' => 6]));
    }
}
