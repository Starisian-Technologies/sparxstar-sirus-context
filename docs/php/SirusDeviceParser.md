# SirusDeviceParser

**Namespace:** `Starisian\Sparxstar\Sirus\services`

**Full Class Name:** `Starisian\Sparxstar\Sirus\services\SirusDeviceParser`

## Description

SirusDeviceParser - Server-side device/UA parsing via Matomo DeviceDetector.
All UA parsing happens server-side. The JS client never bundles a device-detection
library — it sends raw signals, and the server does all interpretation.
@package Starisian\Sparxstar\Sirus
/

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\services;

if (! defined('ABSPATH')) {
    exit;
}

/**
Wraps Matomo DeviceDetector for server-side UA parsing.
Gracefully degrades when the library is not installed — returns an empty
structured result rather than throwing or returning null, so callers never
need to guard for a missing dependency.
Install via: composer require matomo/device-detector

## Methods

### `parse(string $user_agent)`

SirusDeviceParser - Server-side device/UA parsing via Matomo DeviceDetector.
All UA parsing happens server-side. The JS client never bundles a device-detection
library — it sends raw signals, and the server does all interpretation.
@package Starisian\Sparxstar\Sirus
/

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\services;

if (! defined('ABSPATH')) {
    exit;
}

/**
Wraps Matomo DeviceDetector for server-side UA parsing.
Gracefully degrades when the library is not installed — returns an empty
structured result rather than throwing or returning null, so callers never
need to guard for a missing dependency.
Install via: composer require matomo/device-detector
/
final class SirusDeviceParser
{
    /**
Parse a User-Agent string and return structured device information.
Returns an associative array with keys:
  browser, browser_version, os, os_version,
  device_type, brand, model, is_bot
All values are strings except is_bot (bool). Unknown fields are empty strings.
@param string $user_agent The raw User-Agent string to parse.
@return array<string, string|bool>

### `empty_result()`

Returns a zeroed result array with the expected structure.
@return array<string, string|bool>

