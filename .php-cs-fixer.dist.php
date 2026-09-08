<?php

declare(strict_types=1);

/*
 * Symfony coding standards, which apply to every PHP file in this repository.
 *
 * `blender/` and `tools/` are Python and are checked by Ruff instead — see `pyproject.toml`.
 *
 * WHY `@Symfony:risky` IS OFF. Its two loudest rules here are `native_function_invocation` and
 * `native_constant_invocation`, which together wanted 132 changes across the tree to write `\count()`
 * and `\PHP_INT_MAX`. That buys an opcode-level lookup saving in a codebase whose own TODO states that
 * runtime is not a constraint, and it costs a diff in almost every file. The correctness-bearing risky
 * rules are not in that set. `setRiskyAllowed(true)` is nevertheless on, because `declare_strict_types`
 * is classified risky and is wanted: all 169 files carry it today and the rule is what keeps a new one
 * from arriving without it.
 *
 * WHY `phpdoc_to_comment` IS OFF. It rewrites an inline `@var` docblock that does not sit on a
 * structural element into a plain comment, and PHPStan then stops reading it. The solver leans on those
 * hints for its array shapes, so the rule would trade style for a level-5 error.
 */

$finder = (new PhpCsFixer\Finder())
    ->in([__DIR__.'/src', __DIR__.'/tests'])
    ->append([__FILE__, __DIR__.'/bin/console']);

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@Symfony' => true,
        '@PHP83Migration' => true,
        'declare_strict_types' => true,
        'phpdoc_to_comment' => false,
        // The solver documents its array shapes in long `@param` blocks that carry real information, so
        // they are aligned left rather than collapsed.
        'phpdoc_align' => ['align' => 'left'],
    ])
    ->setFinder($finder);
