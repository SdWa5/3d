<?php

declare(strict_types=1);

namespace App\Spec;

/** Shared identifier, colour and component-fit rules for semantic validation. */
final class ValidationRules
{
    public const ID_PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    public const COLOR_PATTERN = '/^#[0-9a-fA-F]{6}$/';

    /** One tenth of a millimetre absorbs floating-point noise at a component boundary. */
    public const FIT_TOLERANCE_M = 0.0001;
}
