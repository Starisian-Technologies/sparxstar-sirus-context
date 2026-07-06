<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPaths(
        array(
            __DIR__ . '/src',
        )
    )
    ->withSkip(
        array(
            '*/vendor/*',
            '*/tests/*',
            '*/node_modules/*',
            '*/wordpress-installer/*',
            '*/wp-admin/*',
            '*/wp-content/*',
            // Table-name interpolation in wpdb queries is intentionally safe (phpcs:ignore
            // WordPress.DB.PreparedSQL.InterpolatedNotPrepared). Rector incorrectly rewrites
            // "{$table}…%d" into "'…' . $table" which appends the table name to the wrong
            // position and breaks the SQL. Skip globally — the phpcs:ignore comments are the
            // correct suppression mechanism for this pattern.
            \Rector\CodingStyle\Rector\Encapsed\EncapsedStringsToSprintfRector::class,
            // ReadOnlyClassRector adds readonly to classes that are extended by anonymous
            // test mocks and WP hook callbacks; safe to defer to a dedicated refactor sprint.
            \Rector\Php82\Rector\Class_\ReadOnlyClassRector::class,
        )
    )
    // Use the modern "Prepared Sets" (Replaces the old LevelSetList/SetList)
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        codingStyle: true,
        typeDeclarations: true,
        // Keep false for WP. Prevents removing 'public' from hook callbacks.
        privatization: false, 
        earlyReturn: true,
        // CAUTION: Set to false if you want to keep empty() checks. 
        // Set to true if you want strict comparisons (===).
        strictBooleans: false, 
    )
    // Targets PHP 8.2 features
    ->withPhpSets(php82: true);
