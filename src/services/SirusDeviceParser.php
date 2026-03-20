<?php

/**
 * SirusDeviceParser - Server-side device/UA parsing via Matomo DeviceDetector.
 *
 * All UA parsing happens server-side. The JS client never bundles a device-detection
 * library — it sends raw signals, and the server does all interpretation.
 *
 * @package Starisian\Sparxstar\Sirus
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\services;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Wraps Matomo DeviceDetector for server-side UA parsing.
 * Gracefully degrades when the library is not installed — returns an empty
 * structured result rather than throwing or returning null, so callers never
 * need to guard for a missing dependency.
 *
 * Install via: composer require matomo/device-detector
 */
final class SirusDeviceParser
{
    /**
     * Parse a User-Agent string and return structured device information.
     *
     * Returns an associative array with keys:
     *   browser, browser_version, os, os_version,
     *   device_type, brand, model, is_bot
     *
     * All values are strings except is_bot (bool). Unknown fields are empty strings.
     *
     * @param string $user_agent The raw User-Agent string to parse.
     * @return array<string, string|bool>
     */
    public function parse(string $user_agent): array
    {
        $empty = $this->empty_result();

        if ($user_agent === '') {
            return $empty;
        }

        if (! class_exists(\DeviceDetector\DeviceDetector::class)) {
            // Matomo library not installed; return empty structure so callers
            // still receive a consistent array shape.
            return $empty;
        }

        try {
            $dd = new \DeviceDetector\DeviceDetector($user_agent);
            $dd->parse();

            if ($dd->isBot()) {
                return array_merge($empty, ['is_bot' => true]);
            }

            $client = $dd->getClient() ?? [];
            $os     = $dd->getOs()     ?? [];

            return [
                'browser'         => sanitize_text_field((string) ($client['name']    ?? '')),
                'browser_version' => sanitize_text_field((string) ($client['version'] ?? '')),
                'os'              => sanitize_text_field((string) ($os['name']         ?? '')),
                'os_version'      => sanitize_text_field((string) ($os['version']      ?? '')),
                'device_type'     => sanitize_text_field((string) $dd->getDeviceName()),
                'brand'           => sanitize_text_field((string) $dd->getBrandName()),
                'model'           => sanitize_text_field((string) $dd->getModel()),
                'is_bot'          => false,
            ];
        } catch (\Throwable $e) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log('[Sirus SirusDeviceParser] DeviceDetector::parse() failed: ' . $e->getMessage());
            return $empty;
        }
    }

    /**
     * Returns a zeroed result array with the expected structure.
     *
     * @return array<string, string|bool>
     */
    private function empty_result(): array
    {
        return [
            'browser'         => '',
            'browser_version' => '',
            'os'              => '',
            'os_version'      => '',
            'device_type'     => '',
            'brand'           => '',
            'model'           => '',
            'is_bot'          => false,
        ];
    }
}
