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
    )
    // Targets PHP 8.2 features
    ->withPhpSets(php82: true)
    // Run single-process. Rector's parallel mode was losing a whole job batch
    // here: CI reported "results for 48 of 73 files are missing", and 48 is
    // exactly the default jobSize, so one worker's batch never came back.
    //
    // This surfaced on the 2.6.7 -> 2.7.0 bump, but 2.7.0 did not break it. Two
    // of that release's changes are "Fix silently skipped files when the only
    // worker is respawned" (rector-src#8521) and "Terminate timed-out and
    // unconnected workers" (#8522); #8521 adds "report an error when results
    // for some files are missing". Before it, a run that lost a batch still
    // exited [OK]. So this check was passing while leaving ~2/3 of src/
    // unanalysed, and the bump only made that visible.
    //
    // The failing run covered 73 files. At that scale parallelism buys little
    // and costs the guarantee that every file was actually looked at, so take
    // the guarantee.
    ->withoutParallel();
