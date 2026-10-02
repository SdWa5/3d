<?php

declare(strict_types=1);

namespace App\Scene;

/**
 * Whether a solved stack turns its horn subs so their mouths meet.
 *
 * Stated in the `stack:` block as `mouths`, because the block is re-solved on every build and a choice that lived on
 * the command line alone would come back as the default the first time anything rebuilt the scene.
 */
enum MouthMode: string
{
    /** Turn every cabinet {@see MouthPairing} can pair. The default, as the owner asked on 2026-10-02. */
    case Paired = 'paired';

    /** Leave every roll as the solve dealt it, which is what every rig before 0.138.0 did. */
    case Free = 'free';
}
