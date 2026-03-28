<?php

/**
 * IpAnonymizer - Anonymizes IP addresses per Sirus privacy requirements.
 *
 * Privacy rules (non-negotiable):
 * - IPv4: last octet zeroed — 192.168.1.x → 192.168.1.0
 * - IPv6: last 80 bits zeroed (/48 prefix retained)
 * - Result is always a valid, sanitized string safe for storage.
 *
 * @package Starisian\Sparxstar\Sirus
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\helpers;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Stateless IP anonymization helper.
 * No instantiation required; all access is via static methods.
 */
final class IpAnonymizer
{
    /** Prevent instantiation. */
    private function __construct()
    {
    }

    /**
     * Anonymizes an IPv4 or IPv6 address.
     *
     * IPv4: zeros the last octet.   192.168.1.42  → 192.168.1.0
     * IPv6: zeros the last 80 bits (retains /48). 2001:db8::1 → 2001:db8::
     *
     * Returns an empty string for invalid or empty input.
     *
     * @param string $ip Raw IP address string.
     * @return string Anonymized IP address, or '' on invalid input.
     */
    public static function anonymize(string $ip): string
    {
        $ip = trim($ip);

        if ($ip === '') {
            return '';
        }

        // Handle IPv4-mapped IPv6 addresses (::ffff:192.168.1.1).
        if (str_starts_with($ip, '::ffff:')) {
            $ip = substr($ip, 7);
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            return self::anonymize_ipv4($ip);
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
            return self::anonymize_ipv6($ip);
        }

        return '';
    }

    /**
     * Returns a /24 (IPv4) or /48 (IPv6) subnet string suitable for use as a
     * fingerprint signal. Never stored — used only as input to a hash function.
     *
     * Delegates to the same private helpers as anonymize() so the logic is
     * defined exactly once.
     *
     * @param string $ip Raw IP address string.
     * @return string Subnet prefix string, or '' for invalid input.
     */
    public static function ipSubnet(string $ip): string
    {
        // ipSubnet produces the same result as anonymize() — the /24 or /48 prefix
        // already represents the correct granularity for fingerprinting.
        return self::anonymize($ip);
    }

    /**
     * @param string $ip Valid IPv4 string.
     * @return string Anonymized IPv4 string.
     */
    private static function anonymize_ipv4(string $ip): string
    {
        $parts    = explode('.', $ip);
        $parts[3] = '0';
        return implode('.', $parts);
    }

    /**
     * Zero the last 80 bits of an IPv6 address (retain /48 prefix).
     *
     * @param string $ip Valid IPv6 string.
     * @return string Anonymized IPv6 string in compressed notation.
     */
    private static function anonymize_ipv6(string $ip): string
    {
        // Expand to full binary.
        $binary = inet_pton($ip);
        if ($binary === false) {
            return '';
        }

        // Zero bytes 6–15 (last 80 bits); keep bytes 0–5 (/48).
        $binary = substr($binary, 0, 6) . str_repeat("\x00", 10);

        $result = inet_ntop($binary);
        return $result !== false ? $result : '';
    }
}
