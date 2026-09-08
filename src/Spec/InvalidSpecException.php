<?php

declare(strict_types=1);

namespace App\Spec;

/**
 * Thrown when a spec file cannot be turned into a DeviceSpec at all — unreadable YAML, a
 * missing required key, a value of the wrong type, an unknown enum value. Semantic problems
 * of an otherwise well-formed spec (impossible dimensions, a rigging point outside the box)
 * are *not* exceptions: SpecValidator collects those so one run can report every problem.
 */
final class InvalidSpecException extends \RuntimeException
{
}
