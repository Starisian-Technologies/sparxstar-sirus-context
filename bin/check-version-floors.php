#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Version-floor drift check.
 *
 * The minimum WordPress and PHP this plugin supports is declared in several
 * places that nothing kept in agreement:
 *
 *   - the "Requires at least" / "Requires PHP" headers, which are what
 *     WordPress itself enforces at activation;
 *   - minimum_wp_version in phpcs.xml.dist, which the WordPress.WP.Deprecated*
 *     sniffs read to decide what counts as a deprecated call;
 *   - minimum_supported_wp_version in phpcs.xml, the same value under its
 *     older WPCS 2.x name;
 *   - testVersion in both rulesets, which PHPCompatibility reads;
 *   - config.platform.php in composer.json, which pins what Composer resolves
 *     dependencies against.
 *
 * They had drifted four ways at once: the headers said WP 6.8 / PHP 8.2, while
 * phpcs.xml said WP 6.4 and PHP 8.1-8.4, and phpcs.xml.dist declared no WP
 * floor at all and so silently used the WPCS built-in default. Every one of
 * those disagreements fails in the same direction -- the linters accept code
 * that the declared runtime floor cannot run -- and none of them is visible
 * in a diff that touches only one of the files.
 *
 * The headers are the source of truth here, because they are the only
 * declaration WordPress acts on. Everything else is checked against them.
 *
 * Usage: php bin/check-version-floors.php
 * Exit 0 = all declarations agree. Exit 1 = drift (details on stderr).
 */

$root = dirname(__DIR__);

/** @var list<string> $errors */
$errors = [];

/**
 * Reads one plugin header field from a file's header docblock.
 *
 * WordPress only parses headers out of the first 8 KB of the file, so that is
 * all this reads -- matching further down would accept a value WordPress
 * itself would never see.
 */
function readPluginHeader(string $path, string $field): ?string
{
    $contents = file_get_contents($path);
    if ($contents === false) {
        return null;
    }
    $header = substr($contents, 0, 8192);
    $pattern = '/^[ \t\/*#@]*' . preg_quote($field, '/') . ':(.*)$/mi';
    if (preg_match($pattern, $header, $matches) !== 1) {
        return null;
    }

    return trim($matches[1]);
}

/**
 * Reads a <config name="..." value="..."/> value out of a PHPCS ruleset.
 */
function readPhpcsConfig(string $path, string $name): ?string
{
    $xml = @simplexml_load_file($path);
    if ($xml === false) {
        return null;
    }
    foreach ($xml->config as $config) {
        if ((string) $config['name'] === $name) {
            return (string) $config['value'];
        }
    }

    return null;
}

// ── Source of truth: the plugin headers ──────────────────────────────────

$canonicalFile = $root . '/sparxstar-sirus-context.php';
$minWp = readPluginHeader($canonicalFile, 'Requires at least');
$minPhp = readPluginHeader($canonicalFile, 'Requires PHP');

if ($minWp === null || $minPhp === null) {
    fwrite(STDERR, "sparxstar-sirus-context.php is missing 'Requires at least' or 'Requires PHP'.\n");
    fwrite(STDERR, "Those headers are the source of truth for every other floor in this repo.\n");
    exit(1);
}

// ── Every file WordPress will list as a plugin must declare both ─────────

// WordPress scans every top-level PHP file in a plugin directory for a
// "Plugin Name:" header and offers each one separately on the plugins screen.
// A file without the floors activates on any environment and fatals there,
// which is how sparxstar-user-environment-check.php shipped with no floor at
// all while the plugin beside it declared 6.8.
foreach (glob($root . '/*.php') ?: [] as $file) {
    if (readPluginHeader($file, 'Plugin Name') === null) {
        continue;
    }
    $name = basename($file);
    foreach (['Requires at least' => $minWp, 'Requires PHP' => $minPhp] as $field => $expected) {
        $value = readPluginHeader($file, $field);
        if ($value === null) {
            $errors[] = "{$name}: has a 'Plugin Name' header but no '{$field}'. WordPress will "
                . "offer it for activation on any environment.";
        } elseif ($value !== $expected) {
            $errors[] = "{$name}: '{$field}' is {$value}, but sparxstar-sirus-context.php declares {$expected}.";
        }
    }
}

// ── PHPCS rulesets ───────────────────────────────────────────────────────

// phpcs.xml.dist is what `composer run lint` uses; phpcs.xml is what
// `composer run phpcs` and `comments:check` use. Both are live, so both are
// checked. WPCS 3.x renamed minimum_supported_wp_version to
// minimum_wp_version and honours either, so accept whichever a file sets --
// but require that at least one is present, since an absent floor silently
// falls back to the WPCS default rather than failing.
$rulesets = ['phpcs.xml', 'phpcs.xml.dist'];

foreach ($rulesets as $ruleset) {
    $path = $root . '/' . $ruleset;
    if (! file_exists($path)) {
        continue;
    }

    $wp = readPhpcsConfig($path, 'minimum_wp_version')
        ?? readPhpcsConfig($path, 'minimum_supported_wp_version');

    if ($wp === null) {
        $errors[] = "{$ruleset}: declares no minimum_wp_version. The WordPress.WP.Deprecated* "
            . "sniffs then use the WPCS built-in default instead of {$minWp}.";
    } elseif ($wp !== $minWp) {
        $errors[] = "{$ruleset}: minimum_wp_version is {$wp}, but the plugin header declares {$minWp}.";
    }

    $testVersion = readPhpcsConfig($path, 'testVersion');
    if ($testVersion === null) {
        $errors[] = "{$ruleset}: declares no testVersion, so PHPCompatibility checks nothing.";
    } elseif (! str_starts_with($testVersion, $minPhp)) {
        $errors[] = "{$ruleset}: testVersion is {$testVersion}, which does not start at the "
            . "{$minPhp} the plugin header declares.";
    }
}

// ── Composer platform pin ────────────────────────────────────────────────

// config.platform.php tells Composer which PHP to resolve against. If it sits
// below the declared floor, Composer will happily pick dependency versions
// that the supported runtime cannot load.
$composerPath = $root . '/composer.json';
$composer = json_decode((string) file_get_contents($composerPath), true);
$platformPhp = $composer['config']['platform']['php'] ?? null;

if (is_string($platformPhp) && version_compare($platformPhp, $minPhp, '<')) {
    $errors[] = "composer.json: config.platform.php is {$platformPhp}, below the {$minPhp} "
        . "the plugin header declares.";
}

// ── Report ───────────────────────────────────────────────────────────────

if ($errors !== []) {
    fwrite(STDERR, "Version-floor check FAILED — the declared minimums disagree with each other:\n\n");
    foreach ($errors as $error) {
        fwrite(STDERR, "  - {$error}\n");
    }
    fwrite(STDERR, "\nThe 'Requires at least' and 'Requires PHP' headers in\n");
    fwrite(STDERR, "sparxstar-sirus-context.php are the source of truth. Bring the others to match,\n");
    fwrite(STDERR, "or change the headers first if the supported floor itself is moving.\n");
    exit(1);
}

echo "Version-floor check passed — WordPress {$minWp} / PHP {$minPhp} declared consistently.\n";
exit(0);
