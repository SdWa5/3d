<?php

declare(strict_types=1);

namespace App\Tests\Spec;

use App\Spec\DeviceSpec;
use App\Spec\InvalidSpecException;
use App\Spec\Roster;
use App\Spec\RosterLoader;
use App\Spec\SpecLoader;
use App\Tests\Support\SpecFactory;
use PHPUnit\Framework\TestCase;

/**
 * Rosters — what a system brings to one event, as counts that override the specs for one run.
 *
 * The committed rosters are read here as well as the synthetic ones, because they are the reason the class exists
 * and a file that no longer parses would otherwise only surface in a sweep somebody runs by hand.
 */
final class RosterTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = SpecFactory::tempDir('sdwa5-3d-rosters-');
    }

    protected function tearDown(): void
    {
        SpecFactory::removeDir($this->dir);
    }

    public function testACountIsReadForEveryDeviceTheFileNames(): void
    {
        $roster = Roster::fromArray([
            'id' => 'an-event',
            'name' => 'An event',
            'brings' => ['a-sub' => 4, 'a-top' => 0],
        ], '/rosters/an-event.yaml');

        self::assertSame('an-event', $roster->id);
        self::assertSame(['a-sub' => 4, 'a-top' => 0], $roster->brings);
    }

    /**
     * **Zero is a count, not an omission**, which is the whole of how a cabinet is left at home. A reader who
     * cannot tell "they are not bringing it" from "the file forgot to mention it" has no roster at all.
     */
    public function testZeroSurvivesAsACountRatherThanBeingDroppedAsEmpty(): void
    {
        $roster = Roster::fromArray([
            'id' => 'an-event',
            'name' => 'An event',
            'brings' => ['a-top' => 0],
        ], '/rosters/an-event.yaml');

        self::assertSame(['a-top' => 0], $roster->brings);
    }

    public function testANegativeCountIsRefused(): void
    {
        $this->expectException(InvalidSpecException::class);
        $this->expectExceptionMessage('brings.a-sub is -1');

        Roster::fromArray(['id' => 'x', 'name' => 'X', 'brings' => ['a-sub' => -1]], '/rosters/x.yaml');
    }

    public function testARosterThatChangesNoCountIsRefused(): void
    {
        $this->expectException(InvalidSpecException::class);
        $this->expectExceptionMessage('brings is empty');

        Roster::fromArray(['id' => 'x', 'name' => 'X', 'brings' => []], '/rosters/x.yaml');
    }

    /**
     * The id names the folder every scene built from the roster is written into, so a file whose name and id
     * disagree would file one event's rigs under another event's name.
     */
    public function testAnIdThatDisagreesWithTheFileNameIsRefused(): void
    {
        file_put_contents($this->dir.'/an-event.yaml', "id: another-event\nname: X\nbrings: { a-sub: 2 }\n");

        $this->expectException(InvalidSpecException::class);
        $this->expectExceptionMessage("id is 'another-event' but the file is named 'an-event.yaml'");

        (new RosterLoader($this->dir))->load('an-event');
    }

    public function testAMissingRosterIsRefusedByName(): void
    {
        $this->expectException(InvalidSpecException::class);
        $this->expectExceptionMessage('no roster named nope');

        (new RosterLoader($this->dir))->load('nope');
    }

    public function testTheAvailableIdsAreTheFileNamesSorted(): void
    {
        file_put_contents($this->dir.'/b-event.yaml', "id: b-event\nname: B\nbrings: { a-sub: 1 }\n");
        file_put_contents($this->dir.'/a-event.yaml', "id: a-event\nname: A\nbrings: { a-sub: 1 }\n");
        file_put_contents($this->dir.'/notes.md', 'not a roster');

        self::assertSame(['a-event', 'b-event'], (new RosterLoader($this->dir))->available());
    }

    public function testTheCommittedRostersParseAndNameOnlyDevicesThatExist(): void
    {
        $projectDir = dirname(__DIR__, 2);
        $loader = new RosterLoader($projectDir.'/rosters');
        $ids = array_map(
            static fn (DeviceSpec $spec): string => $spec->id,
            (new SpecLoader($projectDir.'/specs'))->loadAll()['specs'],
        );

        self::assertNotSame([], $loader->available());
        foreach ($loader->available() as $id) {
            $roster = $loader->load($id);
            foreach (array_keys($roster->brings) as $device) {
                self::assertContains($device, $ids, sprintf('%s names a device that does not exist', $id));
            }
        }
    }
}
