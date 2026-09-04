<?php

declare(strict_types=1);

namespace App\Tests\Scene;

use App\Command\SceneBuildCommand;
use App\Scene\Feasibility;
use App\Scene\SceneLoader;
use App\Scene\SceneSpec;
use PHPUnit\Framework\TestCase;

/**
 * A scene's key, and the derived paths built from it.
 *
 * **This exists because of a defect that shipped in 0.98.0 and was invisible for a release.** The inventory moved
 * out of the generated file name and into a folder, and from that moment 2072 generated scenes shared 589
 * basenames — 421 of those names belonging to two or more inventories. Everything downstream was keyed on the
 * basename, so eleven rigs wrote one `.blend` and one `.png`: the first inventory in sort order won, and the rest
 * were found to have an artifact newer than their own source and skipped as up to date. The failure mode of a
 * wrongly-keyed artifact is a *skipped* rebuild, which looks exactly like a current one.
 */
final class SceneKeyTest extends TestCase
{
    public function testTheKeyIsThePathBelowScenesWithoutTheExtension(): void
    {
        $loader = new SceneLoader('/project/scenes');

        self::assertSame('full-rig-stereo', $loader->keyOf('/project/scenes/full-rig-stereo.yaml'));
        self::assertSame('packs/packed-convoy', $loader->keyOf('/project/scenes/packs/packed-convoy.yaml'));
        self::assertSame(
            'generated/gmss/stacked-1-pooled-free',
            $loader->keyOf('/project/scenes/generated/gmss/stacked-1-pooled-free.yaml'),
        );
    }

    /**
     * **One basename, two inventories, two keys.** The whole change in one assertion.
     */
    public function testTwoInventoriesSharingABasenameGetDifferentKeys(): void
    {
        $loader = new SceneLoader('/project/scenes');
        $name = 'stacked-1-pooled--------free----mixed---alternate-center-possible.yaml';

        self::assertNotSame(
            $loader->keyOf('/project/scenes/generated/gmss/'.$name),
            $loader->keyOf('/project/scenes/generated/sdwa5-sepp/'.$name),
        );
    }

    public function testTheRelativeDirectoryIsEmptyForASceneInTheRoot(): void
    {
        $loader = new SceneLoader('/project/scenes');

        self::assertSame('', $loader->relativeDirOf('/project/scenes/full-rig-stereo.yaml'));
        self::assertSame('generated/psl', $loader->relativeDirOf('/project/scenes/generated/psl/stacked-1.yaml'));
    }

    /**
     * Every derived artifact mirrors the scene's own directory, so a `.blend` is as unique as the scene it comes
     * from. Composed onto whatever directory the caller decided on, `--out-dir` included.
     */
    public function testADerivedDirectoryMirrorsTheScenesOwnDirectory(): void
    {
        $command = new SceneBuildCommand();
        $method = new \ReflectionMethod($command, 'derivedDir');
        $root = dirname(__DIR__, 2);

        $gmss = self::sceneAt($root.'/scenes/generated/gmss/x.yaml');
        $sepp = self::sceneAt($root.'/scenes/generated/sdwa5-sepp/x.yaml');
        $hand = self::sceneAt($root.'/scenes/full-rig-stereo.yaml');

        self::assertSame('/build/scenes/generated/gmss', $method->invoke($command, '/build/scenes', $gmss));
        self::assertSame('/build/scenes/generated/sdwa5-sepp', $method->invoke($command, '/build/scenes', $sepp));
        self::assertSame('/build/scenes', $method->invoke($command, '/build/scenes', $hand));
        self::assertNotSame(
            $method->invoke($command, '/build/scenes', $gmss),
            $method->invoke($command, '/build/scenes', $sepp),
        );
    }

    /**
     * An impossible rig is recognised whether the axis is a name field or a folder.
     *
     * Without the second form, running the sweep with `--folders=feasibility` would silently turn
     * {@see \App\Tests\Scene\ShippedScenesTest} into an assertion that hundreds of deliberately unbuildable rigs
     * stand up — the exclusion is a property of the name, so a name that no longer carries it excludes nothing.
     */
    public function testAnImpossibleRigIsRecognisedAsAFieldOrAsAFolder(): void
    {
        self::assertTrue(Feasibility::isImpossibleId('stacked-1-pooled-free-center-impossible'));
        self::assertTrue(Feasibility::isImpossibleId('generated/gmss/impossible/stacked-1-pooled-free-center'));
        self::assertFalse(Feasibility::isImpossibleId('stacked-1-pooled-free-center-possible'));
        self::assertFalse(Feasibility::isImpossibleId('generated/gmss/possible/stacked-1-pooled-free-center'));
    }

    private static function sceneAt(string $path): SceneSpec
    {
        return new SceneSpec($path, basename($path, '.yaml'), 'A scene', [], [], null);
    }
}
