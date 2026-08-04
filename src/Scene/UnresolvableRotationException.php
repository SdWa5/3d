<?php

declare(strict_types=1);

namespace App\Scene;

/**
 * A composed rotation that cannot be written as a pitch, a roll and a yaw.
 *
 * Only reachable by nesting groups whose turns add up to standing a cabinet on end, where the roll and
 * the yaw become the same turn and there are infinitely many equivalent answers. Thrown rather than
 * resolved, and caught by the compiler so the message can name the placement — the same line
 * {@see SceneCompiler} already draws for `aim` together with `yaw_deg`.
 */
final class UnresolvableRotationException extends \RuntimeException
{
}
