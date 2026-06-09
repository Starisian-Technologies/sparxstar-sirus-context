<?php

/**
 * Smoke test for the Sirus REST API contract and seed fixture.
 *
 * This script intentionally avoids WordPress, Composer autoloading, and network
 * calls so downstream repositories can validate the shared contract before they
 * install Sirus runtime dependencies.
 *
 * @package Starisian\Sparxstar\Sirus\Tests\Smoke
 */

declare(strict_types=1);

/**
 * Absolute path to the repository root used by this standalone smoke test.
 */
const SIRUS_SMOKE_REPO_ROOT = __DIR__ . '/../..';

/**
 * Required Sirus REST operations exposed by the public integration contract.
 */
const SIRUS_REQUIRED_OPERATIONS = [
    'registerDevice'     => [ 'POST', '/device' ],
    'getContext'         => [ 'GET', '/context' ],
    'issuePulse'         => [ 'POST', '/pulse' ],
    'getIdentity'        => [ 'GET', '/identity' ],
    'getSession'         => [ 'GET', '/session' ],
    'recordClientReport' => [ 'POST', '/client-report' ],
];

/**
 * Reads and decodes a JSON document as an associative array.
 *
 * @param string $relativePath Repository-relative JSON file path.
 * @return array<string, mixed> Decoded JSON document.
 */
function sirus_smoke_read_json(string $relativePath): array
{
    $path = SIRUS_SMOKE_REPO_ROOT . '/' . ltrim($relativePath, '/');
    if (! is_file($path)) {
        sirus_smoke_fail("Missing JSON file: {$relativePath}");
    }

    $json = file_get_contents($path);
    if ($json === false) {
        sirus_smoke_fail("Unable to read JSON file: {$relativePath}");
    }

    try {
        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $exception) {
        sirus_smoke_fail("Invalid JSON in {$relativePath}: " . $exception->getMessage());
    }

    if (! is_array($decoded)) {
        sirus_smoke_fail("JSON root must be an object: {$relativePath}");
    }

    return $decoded;
}

/**
 * Emits a failure message and exits with a non-zero status.
 *
 * @param string $message Human-readable failure reason.
 * @return never
 */
function sirus_smoke_fail(string $message): never
{
    fwrite(STDERR, "FAIL: {$message}" . PHP_EOL);
    exit(1);
}

/**
 * Asserts that a condition is true.
 *
 * @param bool   $condition Assertion condition.
 * @param string $message   Human-readable failure reason.
 */
function sirus_smoke_assert(bool $condition, string $message): void
{
    if (! $condition) {
        sirus_smoke_fail($message);
    }
}

/**
 * Returns a nested array value by key path.
 *
 * @param array<string, mixed> $source Source array.
 * @param list<string>        $keys   Ordered keys to traverse.
 * @return mixed
 */
function sirus_smoke_get_path(array $source, array $keys): mixed
{
    $value = $source;

    foreach ($keys as $key) {
        if (! is_array($value) || ! array_key_exists($key, $value)) {
            return null;
        }

        $value = $value[$key];
    }

    return $value;
}

/**
 * Decoded OpenAPI contract document under test.
 *
 * @var array<string, mixed> $contract
 */
$contract = sirus_smoke_read_json('docs/contracts/sirus-api-contract.v1.json');

/**
 * Decoded seed fixture document under test.
 *
 * @var array<string, mixed> $seed
 */
$seed = sirus_smoke_read_json('docs/contracts/sirus-api-seed.v1.json');

sirus_smoke_assert(($contract['openapi'] ?? '') === '3.1.0', 'Contract must declare OpenAPI 3.1.0.');
sirus_smoke_assert(($seed['contract'] ?? '') === 'docs/contracts/sirus-api-contract.v1.json', 'Seed must point at the contract file.');
sirus_smoke_assert(($contract['x-sirus-contract']['pulseVerificationOwner'] ?? '') === 'Helios', 'Pulse verification owner must remain Helios.');
sirus_smoke_assert(($contract['x-sirus-contract']['identityInPulse'] ?? true) === false, 'Contract must forbid identity claims in pulses.');

foreach (SIRUS_REQUIRED_OPERATIONS as $operationId => [ $method, $path ]) {
    $methodKey = strtolower($method);
    $operation = sirus_smoke_get_path($contract, [ 'paths', $path, $methodKey ]);

    sirus_smoke_assert(is_array($operation), "Missing {$method} {$path} operation.");
    sirus_smoke_assert(($operation['operationId'] ?? '') === $operationId, "Unexpected operationId for {$method} {$path}.");
    sirus_smoke_assert(isset($seed['requests'][$operationId]), "Seed missing request for {$operationId}.");
    sirus_smoke_assert(($seed['requests'][$operationId]['method'] ?? '') === $method, "Seed method mismatch for {$operationId}.");
    sirus_smoke_assert(($seed['requests'][$operationId]['path'] ?? '') === $path, "Seed path mismatch for {$operationId}.");
    sirus_smoke_assert(isset($seed['expectedResponses'][$operationId]), "Seed missing expected response for {$operationId}.");
}

$pulseResponses = sirus_smoke_get_path($contract, [ 'paths', '/pulse', 'post', 'responses', '201', 'headers', 'Set-Cookie' ]);
sirus_smoke_assert(is_array($pulseResponses), 'Pulse response must document Set-Cookie.');

$contextDeviceDescription = sirus_smoke_get_path($contract, [ 'components', 'parameters', 'DeviceIdQuery', 'description' ]);
sirus_smoke_assert(is_string($contextDeviceDescription) && str_contains($contextDeviceDescription, 'must match'), 'device_id query parameter must document match enforcement.');

fwrite(STDOUT, 'PASS: Sirus API contract and seed smoke test passed.' . PHP_EOL);
