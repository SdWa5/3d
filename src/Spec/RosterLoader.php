<?php

declare(strict_types=1);

namespace App\Spec;

use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Reads roster files from `rosters/`, one file per event and system.
 *
 * **Deliberately not under `specs/`.** {@see SpecLoader} walks that directory recursively and turns every `.yaml` it
 * finds into a {@see DeviceSpec}, so a roster living there would be read as a device with no geometry and would
 * break the library rather than extend it. A roster is not a spec — it states nothing about an object, only about
 * one occasion — and giving it its own directory says that once instead of teaching two loaders to skip each other.
 *
 * **The file name is the id.** `rosters/innschleife-next-event-thl4.yaml` is `--roster=innschleife-next-event-thl4`,
 * and the `id:` inside has to agree, which is the same rule the device specs follow. It matters more here, because
 * the roster's id names the folder the scenes are written into: a file whose name and id disagree would put one
 * event's rigs under another event's name.
 */
final class RosterLoader
{
    public function __construct(private readonly string $rostersDir)
    {
    }

    /**
     * Every roster id on disk, sorted — what an unknown `--roster` is offered as the alternatives.
     *
     * @return list<string>
     */
    public function available(): array
    {
        if (!is_dir($this->rostersDir)) {
            return [];
        }

        $ids = [];
        foreach ((array)scandir($this->rostersDir) as $entry) {
            if (is_string($entry) && preg_match('/^(.+)\.ya?ml$/', $entry, $matches) === 1) {
                $ids[] = $matches[1];
            }
        }
        sort($ids);

        return $ids;
    }

    /**
     * @throws InvalidSpecException when the file is missing or unreadable, is not a YAML mapping, or states an id
     *                              other than its own file name
     */
    public function load(string $id): Roster
    {
        $file = $this->rostersDir.'/'.$id.'.yaml';
        if (!is_file($file)) {
            throw new InvalidSpecException(sprintf('no roster named %s in %s', $id, $this->rostersDir));
        }

        $contents = @file_get_contents($file);
        if ($contents === false) {
            throw new InvalidSpecException('cannot read file');
        }

        try {
            $data = Yaml::parse($contents);
        } catch (ParseException $e) {
            throw new InvalidSpecException('invalid YAML: '.$e->getMessage(), 0, $e);
        }

        if (!is_array($data) || array_is_list($data)) {
            throw new InvalidSpecException('expected a YAML mapping at the top level');
        }

        /** @var array<string, mixed> $data */
        $roster = Roster::fromArray($data, $file);
        if ($roster->id !== $id) {
            throw new InvalidSpecException(sprintf("id is '%s' but the file is named '%s.yaml'", $roster->id, $id));
        }

        return $roster;
    }
}
