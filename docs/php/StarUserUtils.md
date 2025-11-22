# StarUserUtils

**Namespace:** `Starisian\SparxstarUEC`

**Full Class Name:** `Starisian\SparxstarUEC\StarUserUtils`

## Description

Shared utility helpers and public snapshot accessors
for SPARXSTAR environment diagnostics.
@package SparxstarUserEnvironmentCheck
/

declare(strict_types=1);

namespace Starisian\SparxstarUEC;

use Throwable;

use function __;
use function hash;
use function trim;
use function gmdate;
use function is_ssl;
use function strtok;
use function substr;
use function explode;
use function stripos;
use function is_array;
use function filter_var;
use function preg_match;
use function session_id;
use function strtolower;
use function wp_unslash;
use function esc_url_raw;
use function headers_sent;
use function str_contains;
use function apply_filters;
use function session_start;
use function session_status;
use function wp_json_encode;

use const FILTER_VALIDATE_IP;
use const PHP_SESSION_ACTIVE;

use function get_current_user_id;
use function sanitize_text_field;

use Starisian\SparxstarUEC\helpers\StarLogger;
use Starisian\SparxstarUEC\includes\SparxstarUECCacheHelper;
use Starisian\SparxstarUEC\core\SparxstarUECSnapshotRepository;
use Starisian\SparxstarUEC\includes\SparxstarUECSessionManager;

if (! defined('ABSPATH')) {
    exit;
}

/**
Collection of static helper methods for retrieving sanitized visitor metadata
AND reading environment snapshots as the public API.

## Properties

### `$snapshot_cache`

Shared utility helpers and public snapshot accessors
for SPARXSTAR environment diagnostics.
@package SparxstarUserEnvironmentCheck
/

declare(strict_types=1);

namespace Starisian\SparxstarUEC;

use Throwable;

use function __;
use function hash;
use function trim;
use function gmdate;
use function is_ssl;
use function strtok;
use function substr;
use function explode;
use function stripos;
use function is_array;
use function filter_var;
use function preg_match;
use function session_id;
use function strtolower;
use function wp_unslash;
use function esc_url_raw;
use function headers_sent;
use function str_contains;
use function apply_filters;
use function session_start;
use function session_status;
use function wp_json_encode;

use const FILTER_VALIDATE_IP;
use const PHP_SESSION_ACTIVE;

use function get_current_user_id;
use function sanitize_text_field;

use Starisian\SparxstarUEC\helpers\StarLogger;
use Starisian\SparxstarUEC\includes\SparxstarUECCacheHelper;
use Starisian\SparxstarUEC\core\SparxstarUECSnapshotRepository;
use Starisian\SparxstarUEC\includes\SparxstarUECSessionManager;

if (! defined('ABSPATH')) {
    exit;
}

/**
Collection of static helper methods for retrieving sanitized visitor metadata
AND reading environment snapshots as the public API.
/
final class StarUserUtils
{
    /**
Session namespace key used to avoid collisions with other plugins.
/
    private const SESSION_NAMESPACE = 'sparxstar_env';

    /**
Session storage key for the most recent environment snapshot.
/
    private const SESSION_KEY = 'sparxstar_env_snapshot';

    /**
Runtime cache for the current request's snapshot.

## Methods

### `getFingerprint()`

Shared utility helpers and public snapshot accessors
for SPARXSTAR environment diagnostics.
@package SparxstarUserEnvironmentCheck
/

declare(strict_types=1);

namespace Starisian\SparxstarUEC;

use Throwable;

use function __;
use function hash;
use function trim;
use function gmdate;
use function is_ssl;
use function strtok;
use function substr;
use function explode;
use function stripos;
use function is_array;
use function filter_var;
use function preg_match;
use function session_id;
use function strtolower;
use function wp_unslash;
use function esc_url_raw;
use function headers_sent;
use function str_contains;
use function apply_filters;
use function session_start;
use function session_status;
use function wp_json_encode;

use const FILTER_VALIDATE_IP;
use const PHP_SESSION_ACTIVE;

use function get_current_user_id;
use function sanitize_text_field;

use Starisian\SparxstarUEC\helpers\StarLogger;
use Starisian\SparxstarUEC\includes\SparxstarUECCacheHelper;
use Starisian\SparxstarUEC\core\SparxstarUECSnapshotRepository;
use Starisian\SparxstarUEC\includes\SparxstarUECSessionManager;

if (! defined('ABSPATH')) {
    exit;
}

/**
Collection of static helper methods for retrieving sanitized visitor metadata
AND reading environment snapshots as the public API.
/
final class StarUserUtils
{
    /**
Session namespace key used to avoid collisions with other plugins.
/
    private const SESSION_NAMESPACE = 'sparxstar_env';

    /**
Session storage key for the most recent environment snapshot.
/
    private const SESSION_KEY = 'sparxstar_env_snapshot';

    /**
Runtime cache for the current request's snapshot.
/
    private static ?array $snapshot_cache = null;

    // -------------------------------------------------------------------------
    // I. IDENTITY HELPERS (MODERN MODEL)
    // -------------------------------------------------------------------------

    /**
Return the stable plugin fingerprint used by JS + DB.
Priority: header → cookie → IP hash.

### `getDeviceHash()`

Return stable device hash used as second identity key.
Priority: header → UA+IP hash.

### `get_snapshot(?int $user_id = null, ?string $session_id = null)`

Retrieve the latest stored snapshot for a user/session (public).

### `fetch_snapshot(?int $user_id, ?string $session_id)`

Internal engine: fetch the full snapshot from runtime cache, object cache, or database.

### `flush_cache(?int $user_id = null, ?string $session_id = null)`

Generic "dot path" accessor into the snapshot structure.
/
    private static function get_value_from_snapshot(
        string $path,
        mixed $default,
        ?int $user_id,
        ?string $session_id
    ): mixed {
        $snapshot = self::fetch_snapshot($user_id, $session_id);
        if ($snapshot === null) {
            return $default;
        }

        $current = $snapshot;
        foreach (explode('.', $path) as $key) {
            if (! is_array($current) || ! isset($current[$key])) {
                return $default;
            }

            $current = $current[$key];
        }

        return $current;
    }

    /**
Flushes the cache for a given user, forcing the next getter call to fetch fresh data.

### `get_full_snapshot(?int $user_id = null, ?string $session_id = null)`

Retrieves the entire raw snapshot for debugging or full-data use cases.

### `get_visitor_id(?int $user_id = null, ?string $session_id = null)`

Get the user's stable, anonymous browser fingerprint ID from the snapshot.
This is the primary key for tracking anonymous users.

### `ensure_session()`

Ensure a PHP session is initialised before attempting to read or write data.

### `initialise_namespace()`

Guarantee that the plugin-specific session namespace exists.

### `get_server_value(string $key)`

Retrieve a value from the $_SERVER superglobal with sanitization applied.

### `filter_ip_address(?string $value)`

Sanitize and validate an IP address string.

### `getClientIP()`

Retrieve the client IP address considering proxy headers.

### `get_current_visitor_ip()`

Internal helper to get the LIVE IP of the CURRENT visitor.
(alias for snapshot-independent IP lookup).

### `setSessionValue(string $key, mixed $value)`

Persist an arbitrary value in the session namespace.

### `getSessionValue(string $key, mixed $default = null)`

Retrieve a value from the session namespace.

### `storeEnvironmentSnapshot(array $snapshot, array $context = [])`

Store an environment snapshot and its context within the PHP session.

### `getEnvironmentSnapshot()`

Retrieve the stored environment snapshot from the PHP session.

### `getSessionID()`

Retrieve the active PHP session identifier when available.

### `getUserAgent()`

Access the current user agent string with sanitization applied.

### `getCurrentURL()`

Build the current request URL using sanitized server globals.

### `getReferrerURL()`

Retrieve the referring URL from the request headers.

### `getIPGeoLocation(string $ip = '')`

Fetch geolocation data using an external provider hooked via WordPress filters.

### `getGeoLocationData(string $field = '', string $ip = '')`

Retrieve a specific field from the geolocation payload or the full JSON
from the external provider (snapshot-independent).

### `getUserLanguage(string $ret_type = 'code')`

Determine the preferred language from the Accept-Language header.

### `getUserOS()`

Determine the visitor operating system based on the User-Agent string.

### `getUserBrowser()`

Get the approximate browser name based on the User-Agent string.

### `isBot()`

Determine if the request is from a bot/crawler using common user agent patterns.

### `getRequestMethod()`

Retrieve the current HTTP method (GET, POST, etc.).

### `isAjax()`

Check if the current request is an AJAX request.

### `getWpEnvironmentType()`

Get the current WordPress environment type.

### `allow_snapshot_if_none_exist()`

Allow snapshot creation if none exists for current identity.
Clears the session block flag when admin visits settings but no snapshot exists.

