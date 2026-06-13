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
            // Files whose $wpdb->prepare() patterns Rector has historically mangled.
            // The codingStyle set converts `prepare('SQL ' . $table, $val)` into
            //   `prepare('SQL %s', $val)` . $table  — which swaps the table name
            // into the placeholder slot at runtime. Until we identify and disable
            // the specific rule(s), skip these files entirely. Their queries use
            // an established sprintf($table) + %%d/%%s pattern that is already safe.
            __DIR__ . '/src/core/SirusEventRepository.php',
            __DIR__ . '/src/core/SirusEventAggregator.php',
            __DIR__ . '/src/core/SirusRuleHitRepository.php',
            __DIR__ . '/src/core/SirusMitigationActionRepository.php',
            // Files whose `!== null` checks Rector flips to fully-qualified
            // `instanceof \Some\Long\Namespace\Class`, which is uglier and
            // adds zero safety since the property/return types already enforce it.
            __DIR__ . '/src/core/DeviceContinuity.php',
            __DIR__ . '/src/services/EnvironmentResolver.php',
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
