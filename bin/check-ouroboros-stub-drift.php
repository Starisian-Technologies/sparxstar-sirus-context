#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * D-4: Ouroboros stub-drift check.
 *
 * Sirus does not maintain local copies of the Ouroboros primitives it
 * consumes (TrustLevelPrimitive, ContextPulse, Platform, ContextBootException,
 * ContextPulseSigningMaterial) — it imports and calls them directly. That
 * means Sirus's own code effectively encodes an implicit "stub" of each
 * primitive's shape: which enum cases it switches on, which constructor
 * parameter names it passes, which methods/constants it calls.
 *
 * If the REAL installed starisian/sparxstar-ouroboros-integrity package ever
 * changes that shape (a renamed parameter, a removed enum case, a retyped
 * argument), Sirus's live-path code breaks — sometimes only at runtime, not
 * at static-analysis time, because PHP resolves enum cases, named
 * constructor arguments, and method calls dynamically.
 *
 * This happened for real: see docs/DRAFT-OQ-016-trustlevelprimitive-drift.md,
 * where an Ouroboros v2.0.0 enum-case change caused 94 CI failures with
 * ValueError at runtime, discovered only because the test suite happened to
 * exercise the affected call sites. This script is the pre-flight check that
 * should have caught that class of drift before test time.
 *
 * Scope: this is intentionally pragmatic, not exhaustive. It only checks the
 * Ouroboros primitives Sirus's src/ tree actually imports today (see the
 * `use Starisian\Sparxstar\Infrastructure\...` grep this file's expectations
 * were derived from). CredentialTier is excluded — Sirus still ships a local
 * stub for it (src/Infrastructure/DTOs/CredentialTier.php) pending Ouroboros
 * promoting a canonical version (tracked in TRACKER.md); there is nothing to
 * diff it against yet.
 *
 * Usage: php bin/check-ouroboros-stub-drift.php
 * Exit 0 = no drift detected. Exit 1 = drift detected (details on stdout/stderr).
 */

$autoload = __DIR__ . '/../vendor/autoload.php';
if (! file_exists($autoload)) {
    fwrite(STDERR, "vendor/autoload.php not found — run `composer install` first.\n");
    exit(1);
}
require $autoload;

use Starisian\Sparxstar\Infrastructure\Constants\Platform;
use Starisian\Sparxstar\Infrastructure\DTOs\ContextPulse;
use Starisian\Sparxstar\Infrastructure\DTOs\TrustLevelPrimitive;
use Starisian\Sparxstar\Infrastructure\Exceptions\ContextBootException;
use Starisian\Sparxstar\Infrastructure\Utils\ContextPulseSigningMaterial;

/** @var list<string> $errors */
$errors = [];

/**
 * Verifies a backed enum has exactly the expected cases with the expected
 * backing values. Extra cases on the installed package are not a failure
 * (additive changes don't break existing consumers) — only missing or
 * renamed/retyped cases are.
 *
 * @param array<string, string> $expectedCases Case name => expected backing value.
 * @param list<string> $errors
 */
function checkEnumCases(string $enumClass, array $expectedCases, array &$errors): void
{
    if (! enum_exists($enumClass)) {
        $errors[] = "{$enumClass}: expected an enum, but it is missing or not an enum in the installed package.";
        return;
    }

    foreach ($expectedCases as $name => $expectedValue) {
        if (! defined("{$enumClass}::{$name}")) {
            $errors[] = "{$enumClass}::{$name}: expected enum case is missing from the installed package.";
            continue;
        }

        /** @var \UnitEnum|\BackedEnum $case */
        $case = constant("{$enumClass}::{$name}");
        $actualValue = $case instanceof \BackedEnum ? $case->value : null;

        if ($actualValue !== $expectedValue) {
            $errors[] = "{$enumClass}::{$name}: expected backing value '{$expectedValue}', got "
                . var_export($actualValue, true) . '.';
        }
    }
}

/**
 * Verifies a class constructor has each expected named parameter with the
 * expected type. Order is not checked — Sirus always calls these
 * constructors with named arguments, so parameter order is not load-bearing,
 * but a renamed or retyped parameter breaks the named-argument call site.
 *
 * @param array<string, string> $expectedParams Parameter name => expected type name.
 * @param list<string> $errors
 */
function checkConstructorParams(string $class, array $expectedParams, array &$errors): void
{
    if (! class_exists($class)) {
        $errors[] = "{$class}: expected class is missing from the installed package.";
        return;
    }

    $ctor = (new ReflectionClass($class))->getConstructor();
    if ($ctor === null) {
        $errors[] = "{$class}: expected a constructor, but none was found.";
        return;
    }

    $actualParams = [];
    foreach ($ctor->getParameters() as $param) {
        $type = $param->getType();
        $actualParams[$param->getName()] = $type instanceof ReflectionNamedType ? $type->getName() : (string) $type;
    }

    foreach ($expectedParams as $name => $expectedType) {
        if (! array_key_exists($name, $actualParams)) {
            $errors[] = "{$class}::__construct(): expected parameter \${$name} is missing.";
            continue;
        }

        if ($actualParams[$name] !== $expectedType) {
            $errors[] = "{$class}::__construct(): parameter \${$name} expected type '{$expectedType}', "
                . "got '{$actualParams[$name]}'.";
        }
    }
}

/**
 * Verifies a method exists with the expected staticness, ordered parameter
 * types, and return type.
 *
 * @param list<string> $expectedParamTypes
 * @param list<string> $errors
 */
function checkMethodSignature(
    string $class,
    string $method,
    bool $expectedStatic,
    array $expectedParamTypes,
    ?string $expectedReturnType,
    array &$errors
): void {
    if (! class_exists($class)) {
        $errors[] = "{$class}: expected class is missing from the installed package.";
        return;
    }

    if (! method_exists($class, $method)) {
        $errors[] = "{$class}::{$method}(): method is missing from the installed package.";
        return;
    }

    $reflection = new ReflectionMethod($class, $method);

    if ($reflection->isStatic() !== $expectedStatic) {
        $errors[] = "{$class}::{$method}(): expected static=" . ($expectedStatic ? 'true' : 'false')
            . ', got static=' . ($reflection->isStatic() ? 'true' : 'false') . '.';
    }

    $actualParamTypes = array_map(
        static function (ReflectionParameter $param): string {
            $type = $param->getType();
            return $type instanceof ReflectionNamedType ? $type->getName() : (string) $type;
        },
        $reflection->getParameters()
    );

    if ($actualParamTypes !== $expectedParamTypes) {
        $errors[] = "{$class}::{$method}(): expected parameter types ["
            . implode(', ', $expectedParamTypes) . '], got [' . implode(', ', $actualParamTypes) . '].';
    }

    if ($expectedReturnType !== null) {
        $returnType   = $reflection->getReturnType();
        $actualReturn = $returnType instanceof ReflectionNamedType ? $returnType->getName() : null;

        if ($actualReturn !== $expectedReturnType) {
            $errors[] = "{$class}::{$method}(): expected return type '{$expectedReturnType}', got '"
                . ($actualReturn ?? 'none') . "'.";
        }
    }
}

/**
 * Verifies a class constant exists with the expected value.
 *
 * @param list<string> $errors
 */
function checkConstant(string $class, string $name, mixed $expectedValue, array &$errors): void
{
    if (! class_exists($class)) {
        $errors[] = "{$class}: expected class is missing from the installed package.";
        return;
    }

    if (! defined("{$class}::{$name}")) {
        $errors[] = "{$class}::{$name}: expected constant is missing from the installed package.";
        return;
    }

    $actual = constant("{$class}::{$name}");
    if ($actual !== $expectedValue) {
        $errors[] = "{$class}::{$name}: expected " . var_export($expectedValue, true)
            . ', got ' . var_export($actual, true) . '.';
    }
}

// ── TrustLevelPrimitive — cases switched on by StepUpPolicy/PulseGenerator/ContextEngine ──
checkEnumCases(
    TrustLevelPrimitive::class,
    [
        'NORMAL'           => 'NORMAL',
        'STEP_UP_REQUIRED' => 'STEP_UP_REQUIRED',
        'LOCKED'           => 'LOCKED',
    ],
    $errors
);

// ── ContextPulse — constructed with named arguments by PulseGenerator ──────
checkConstructorParams(
    ContextPulse::class,
    [
        'pulse_version'           => 'string',
        'pulse_id'                => 'string',
        'context_id'              => 'string',
        'device_id'               => 'string',
        'session_id'              => 'string',
        'site_id'                 => 'string',
        'network_id'              => 'string',
        'trust_score'             => 'float',
        'trust_level'             => TrustLevelPrimitive::class,
        'behavior_flags'          => 'array',
        'geo_zone'                => 'string',
        'network_effective_type'  => 'string',
        'session_duration'        => 'int',
        'issued_at'               => 'int',
        'expires'                 => 'int',
        'sig'                     => 'string',
    ],
    $errors
);

// ── Platform — constants read by PulseGenerator ─────────────────────────────
checkConstant(Platform::class, 'PULSE_VERSION_CURRENT', '1', $errors);
checkConstant(Platform::class, 'PULSE_MIN_SIGNING_KEY_BYTES', 32, $errors);

// ── ContextPulseSigningMaterial — the shared HMAC signing contract ─────────
checkConstant(ContextPulseSigningMaterial::class, 'VERSION', 1, $errors);
checkMethodSignature(
    ContextPulseSigningMaterial::class,
    'build',
    true,
    [ContextPulse::class],
    'string',
    $errors
);

// ── ContextBootException — thrown/rethrown by ContextEngine::current() ─────
if (! class_exists(ContextBootException::class)) {
    $errors[] = ContextBootException::class . ': expected class is missing from the installed package.';
} elseif (! is_subclass_of(ContextBootException::class, \RuntimeException::class)) {
    $errors[] = ContextBootException::class . ': expected to extend \RuntimeException.';
} else {
    // ContextEngine constructs it as new ContextBootException($message, $code, $previous).
    // It declares no constructor override, so this also verifies the inherited
    // \Exception constructor shape is still (string, int, ?Throwable) compatible.
    $previous = new \RuntimeException('previous');
    $exception = new ContextBootException('message', 7, $previous);

    if ($exception->getMessage() !== 'message') {
        $errors[] = ContextBootException::class . ': constructor did not accept $message as the first argument.';
    }
    if ($exception->getCode() !== 7) {
        $errors[] = ContextBootException::class . ': constructor did not accept $code as the second argument.';
    }
    if ($exception->getPrevious() !== $previous) {
        $errors[] = ContextBootException::class . ': constructor did not accept $previous as the third argument.';
    }
}

// ── Report ───────────────────────────────────────────────────────────────

if ($errors !== []) {
    fwrite(STDERR, "Ouroboros stub-drift check FAILED — the installed starisian/sparxstar-ouroboros-integrity\n");
    fwrite(STDERR, "package no longer matches the shape this repo's code assumes:\n\n");
    foreach ($errors as $error) {
        fwrite(STDERR, "  - {$error}\n");
    }
    fwrite(STDERR, "\nSee docs/DRAFT-OQ-016-trustlevelprimitive-drift.md for the resolution playbook\n");
    fwrite(STDERR, "(the same class of drift this check exists to catch), and bin/check-ouroboros-stub-drift.php\n");
    fwrite(STDERR, "for the exact expectations checked.\n");
    exit(1);
}

echo "Ouroboros stub-drift check passed — all consumed primitives match the installed package.\n";
exit(0);
