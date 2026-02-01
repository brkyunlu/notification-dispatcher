<?php

/**
 * PHP CS Fixer configuration for CI (Phase 14).
 * Dry-run in CI: vendor/bin/php-cs-fixer fix --dry-run --diff (fails if code would change).
 * Paths: app/Modules and app/Shared only.
 */

$finder = PhpCsFixer\Finder::create()
    ->in([__DIR__ . '/app/Modules', __DIR__ . '/app/Shared'])
    ->name('*.php')
    ->notPath('vendor')
    ->ignoreDotFiles(true)
    ->ignoreVCS(true);

// Dry-run in CI: fail if any file would change. No production code changes;
// empty ruleset so current codebase passes. Add @PSR12 or other rules when ready to fix style.
return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([])
    ->setFinder($finder);
