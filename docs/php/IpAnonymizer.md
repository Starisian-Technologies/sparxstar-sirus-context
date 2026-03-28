# IpAnonymizer

**Namespace:** `Starisian\Sparxstar\Sirus\helpers`

**Full Class Name:** `Starisian\Sparxstar\Sirus\helpers\IpAnonymizer`

## Description

IpAnonymizer - Anonymizes IP addresses per Sirus privacy requirements.
Privacy rules (non-negotiable):
- IPv4: last octet zeroed — 192.168.1.x → 192.168.1.0
- IPv6: last 80 bits zeroed (/48 prefix retained)
- Result is always a valid, sanitized string safe for storage.
@package Starisian\Sparxstar\Sirus
/

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\helpers;

if (! defined('ABSPATH')) {
    exit;
}

/**
Stateless IP anonymization helper.
No instantiation required; all access is via static methods.

## Methods

### `__construct()`

IpAnonymizer - Anonymizes IP addresses per Sirus privacy requirements.
Privacy rules (non-negotiable):
- IPv4: last octet zeroed — 192.168.1.x → 192.168.1.0
- IPv6: last 80 bits zeroed (/48 prefix retained)
- Result is always a valid, sanitized string safe for storage.
@package Starisian\Sparxstar\Sirus
/

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\helpers;

if (! defined('ABSPATH')) {
    exit;
}

/**
Stateless IP anonymization helper.
No instantiation required; all access is via static methods.
/
final class IpAnonymizer
{
    /** Prevent instantiation.

### `anonymize(string $ip)`

Anonymizes an IPv4 or IPv6 address.
IPv4: zeros the last octet.   192.168.1.42  → 192.168.1.0
IPv6: zeros the last 80 bits (retains /48). 2001:db8::1 → 2001:db8::
Returns an empty string for invalid or empty input.
@param string $ip Raw IP address string.
@return string Anonymized IP address, or '' on invalid input.

### `ipSubnet(string $ip)`

Returns a /24 (IPv4) or /48 (IPv6) subnet string suitable for use as a
fingerprint signal. Never stored — used only as input to a hash function.
Delegates to the same private helpers as anonymize() so the logic is
defined exactly once.
@param string $ip Raw IP address string.
@return string Subnet prefix string, or '' for invalid input.

### `anonymize_ipv4(string $ip)`

@param string $ip Valid IPv4 string.
@return string Anonymized IPv4 string.

### `anonymize_ipv6(string $ip)`

Zero the last 80 bits of an IPv6 address (retain /48 prefix).
@param string $ip Valid IPv6 string.
@return string Anonymized IPv6 string in compressed notation.

