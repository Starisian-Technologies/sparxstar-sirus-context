<?php

/**
 * Reflection shims for OPTIONAL runtime dependencies, loaded by PHPStan only
 * (via bootstrapFiles in phpstan.neon). Never autoloaded or executed at runtime.
 *
 * These packages are intentionally absent from `composer install` in CI:
 *   - matomo/device-detector is a `suggest` (see composer.json); EnvironmentResolver
 *     guards its use in a try/catch and falls back when it is missing.
 *   - Action Scheduler ships with host plugins (e.g. WooCommerce); the scheduler
 *     calls its functions only behind function_exists() guards.
 *
 * Declaring them here gives the analyzer signatures without forcing the packages
 * into require-dev. Guards keep these no-ops if a real implementation is present.
 */

namespace DeviceDetector {

    if (! class_exists(__NAMESPACE__ . '\\DeviceDetector')) {
        class DeviceDetector
        {
            public function __construct(string $userAgent = '')
            {
            }

            public function parse(): void
            {
            }

            /** @return array<string, mixed>|string|null */
            public function getClient(?string $attr = null)
            {
                return null;
            }

            /** @return array<string, mixed>|string|null */
            public function getOs(?string $attr = null)
            {
                return null;
            }

            public function getDeviceName(): ?string
            {
                return null;
            }

            public function isSmartphone(): bool
            {
                return false;
            }

            public function isTablet(): bool
            {
                return false;
            }

            public function isDesktop(): bool
            {
                return false;
            }

            public function isBot(): bool
            {
                return false;
            }

            // Loosely typed on purpose: the calling code defensively wraps these
            // in is_string(), which a concrete `string` return type would flag as
            // alwaysTrue. Real Matomo returns string|null-ish values here.
            public function getBrandName()
            {
                return '';
            }

            public function getModel()
            {
                return '';
            }
        }
    }
}

namespace {

    if (! function_exists('as_next_scheduled_action')) {
        /**
         * @param string                   $hook
         * @param array<int|string, mixed> $args
         * @param string                   $group
         * @return int|bool
         */
        function as_next_scheduled_action($hook, $args = array(), $group = '')
        {
            return false;
        }
    }

    if (! function_exists('as_schedule_recurring_action')) {
        /**
         * @param int                      $timestamp
         * @param int                      $interval_in_seconds
         * @param string                   $hook
         * @param array<int|string, mixed> $args
         * @param string                   $group
         * @return int
         */
        function as_schedule_recurring_action($timestamp, $interval_in_seconds, $hook, $args = array(), $group = '')
        {
            return 0;
        }
    }

    if (! function_exists('as_unschedule_all_actions')) {
        /**
         * @param string                   $hook
         * @param array<int|string, mixed> $args
         * @param string                   $group
         */
        function as_unschedule_all_actions($hook = '', $args = array(), $group = '')
        {
        }
    }
}
