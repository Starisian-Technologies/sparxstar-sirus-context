<?php

/**
 * Resolves shared Ouroboros pulse contract types and constants.
 *
 * @package Starisian\Sparxstar\Sirus
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\core;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Centralizes the shared ContextPulse contract lookups used by Sirus.
 */
final class OuroborosPulseContract
{
    /**
     * Resolves the current platform pulse version from Ouroboros.
     *
     * @return int|string Int for current numeric platform versions; string retained for
     *                    compatibility with any upstream rollout builds that expose a
     *                    string-backed version constant.
     */
    public static function resolvePulseVersion(): int|string
    {
        $platform_class = self::resolvePlatformClass();

        /** @var int|string $pulse_version */
        $pulse_version = $platform_class::PULSE_VERSION_CURRENT;

        return $pulse_version;
    }

    /**
     * Resolves the minimum signing key length from Ouroboros.
     */
    public static function resolveMinimumSigningKeyBytes(): int
    {
        $platform_class = self::resolvePlatformClass();

        return (int) $platform_class::PULSE_MIN_SIGNING_KEY_BYTES;
    }

    /**
     * Converts a Sirus trust level string to the canonical Ouroboros primitive.
     *
     * @return \BackedEnum Canonical Ouroboros TrustLevelPrimitive instance.
     */
    public static function resolveTrustLevelPrimitive(string $trust_level): \BackedEnum
    {
        $enum_class = self::resolveTrustLevelPrimitiveClass();

        try {
            /** @var \BackedEnum $primitive */
            $primitive = $enum_class::from($trust_level);

            return $primitive;
        } catch (\ValueError $exception) {
            throw new \RuntimeException(
                '[Sirus] Unable to resolve TrustLevelPrimitive for trust level "' . $trust_level . '".',
                0,
                $exception
            );
        }
    }

    /**
     * Extracts the scalar wire value from a pulse trust level primitive.
     */
    public static function trustLevelValue(\BackedEnum|string $trust_level): string
    {
        if ($trust_level instanceof \BackedEnum) {
            return (string) $trust_level->value;
        }

        return (string) $trust_level;
    }

    /**
     * Resolves the canonical Ouroboros Platform class.
     *
     * @return class-string
     */
    private static function resolvePlatformClass(): string
    {
        $platform_class = 'Starisian\\Sparxstar\\Infrastructure\\Utils\\Platform';

        if (! class_exists($platform_class)) {
            throw new \RuntimeException('[Sirus] Ouroboros Platform class is unavailable.');
        }

        return $platform_class;
    }

    /**
     * Resolves the canonical TrustLevelPrimitive enum class.
     *
     * During the PAM-003 rollout, tolerate both known package layouts.
     *
     * @return class-string
     */
    private static function resolveTrustLevelPrimitiveClass(): string
    {
        // TODO(PAM-003): Remove the legacy Primitives fallback once every supported
        // environment exposes TrustLevelPrimitive only from Infrastructure\DTOs.
        $candidates = [
            'Starisian\\Sparxstar\\Infrastructure\\DTOs\\TrustLevelPrimitive',
            'Starisian\\Sparxstar\\Infrastructure\\Primitives\\TrustLevelPrimitive',
        ];

        foreach ($candidates as $enum_class) {
            if (enum_exists($enum_class) && is_subclass_of($enum_class, \BackedEnum::class, true)) {
                return $enum_class;
            }
        }

        throw new \RuntimeException('[Sirus] Ouroboros TrustLevelPrimitive enum is unavailable.');
    }
}
