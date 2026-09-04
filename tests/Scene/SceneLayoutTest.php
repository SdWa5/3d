<?php

declare(strict_types=1);

namespace App\Tests\Scene;

use App\Scene\SceneLayout;
use PHPUnit\Framework\TestCase;

/**
 * Which axes are directories and which are name fields — SWP-3's third ask, as a pure function.
 *
 * **The rule the whole feature is about: an axis value appears in the path or in the name and never in both.**
 * Otherwise every path states the same fact twice and a rename has two places to go wrong.
 */
final class SceneLayoutTest extends TestCase
{
    /** Every axis of one real rig, padded as the name pads them. */
    private const PADDED = [
        'inventory' => 'gmss',
        'stacks' => '1',
        'systems' => 'pooled-------',   // padded to `systems-apart`, the widest case
        'shape' => 'pyramid',
        'orientation' => 'upright',
        'mirror-style' => 'alternate',
        'align' => 'center',
        'feasibility' => 'possible',
    ];

    /** The same rig unpadded, which is what a directory carries. */
    // `+` keeps the left operand's key, so the override has to come first.
    private const RAW = ['systems' => 'pooled'] + self::PADDED;

    /**
     * **The acceptance test for the whole change: the default layout reproduces today's names byte for byte.**
     * Pinned against a real committed file, so the mechanism cannot ship having quietly moved 2072 scenes.
     */
    public function testTheDefaultLayoutReproducesTheNamesOnDiskExactly(): void
    {
        $layout = SceneLayout::of([]);
        self::assertInstanceOf(SceneLayout::class, $layout);

        self::assertSame(
            'stacked-1-pooled--------pyramid-upright-alternate-center-possible',
            $layout->nameFor('stacked', self::PADDED),
        );
        self::assertSame('gmss', $layout->pathFor(self::RAW));
        self::assertTrue($layout->isDefault());
    }

    /**
     * An axis that becomes a directory leaves the name, in the same position, and everything else stays where it
     * was — so switching a level on is a move rather than a rewrite.
     */
    public function testAnAxisIsInThePathOrInTheNameAndNeverInBoth(): void
    {
        $layout = SceneLayout::of(['shape,feasibility']);
        self::assertInstanceOf(SceneLayout::class, $layout);

        $name = $layout->nameFor('stacked', self::PADDED);
        $path = $layout->pathFor(self::RAW);

        self::assertSame('stacked-1-pooled--------upright-alternate-center', $name);
        self::assertSame('gmss/pyramid/possible', $path);
        foreach (['pyramid', 'possible'] as $moved) {
            self::assertStringNotContainsString($moved, $name, 'a folder value is still in the name');
        }
        self::assertStringNotContainsString('upright', $path, 'a name value is in the path');
    }

    /**
     * A comma list is a set. The nesting order is fixed, so the order it is typed in cannot decide where a file
     * lands — `--folders=shape,stacks` and `--folders=stacks,shape` are the same layout.
     */
    public function testTheOrderIsTheSweepsOrderWhicheverOrderItIsTypedIn(): void
    {
        $one = SceneLayout::of(['shape,stacks']);
        $other = SceneLayout::of(['stacks', 'shape']);
        self::assertInstanceOf(SceneLayout::class, $one);
        self::assertInstanceOf(SceneLayout::class, $other);

        self::assertSame(['inventory', 'stacks', 'shape'], $one->folders);
        self::assertSame($one->folders, $other->folders);
        self::assertSame($one->pathFor(self::RAW), $other->pathFor(self::RAW));
    }

    /**
     * The inventory is a level whether or not anybody names it: it is the one axis whose value cannot be recovered
     * from a scene's own recorded command line, which names cabinets rather than owners.
     */
    public function testTheInventoryIsAlwaysADirectory(): void
    {
        $layout = SceneLayout::of(['align']);
        self::assertInstanceOf(SceneLayout::class, $layout);

        self::assertContains('inventory', $layout->folders);
        self::assertStringStartsWith('gmss/', $layout->pathFor(self::RAW));
    }

    public function testAnUnknownAxisIsRefusedAndNamesTheAxesThereAre(): void
    {
        $result = SceneLayout::of(['nope']);

        self::assertIsString($result);
        self::assertStringContainsString("unknown axis 'nope'", $result);
        self::assertStringContainsString('mirror-style', $result);
    }

    /**
     * Four levels is a trie rather than a tree — more directories than files, every leaf holding one scene. It is
     * refused rather than silently produced, the same way `--max-scenes` refuses rather than truncating.
     */
    public function testMoreThanThreeLevelsIsRefused(): void
    {
        $result = SceneLayout::of(['shape,align,stacks,systems']);

        self::assertIsString($result);
        self::assertStringContainsString('At most 3', $result);
    }

    /** What a replay has to be told, and nothing when the layout is the one it would have used anyway. */
    public function testOnlyANonDefaultLayoutHasSomethingToRecord(): void
    {
        $default = SceneLayout::of([]);
        $stated = SceneLayout::of(['feasibility']);
        self::assertInstanceOf(SceneLayout::class, $default);
        self::assertInstanceOf(SceneLayout::class, $stated);

        self::assertTrue($default->isDefault());
        self::assertFalse($stated->isDefault());
        self::assertSame('inventory,feasibility', $stated->stated());
    }
}
