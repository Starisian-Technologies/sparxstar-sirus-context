# DeviceRepository

**Namespace:** `Starisian\Sparxstar\Sirus\core`

**Full Class Name:** `Starisian\Sparxstar\Sirus\core\DeviceRepository`

## Methods

### `__construct(private \wpdb $wpdb)`

DeviceRepository - Persistence layer for DeviceRecord objects.
@package Starisian\Sparxstar\Sirus
/

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\core;

if (! defined('ABSPATH')) {
    exit;
}

/**
Handles all database interactions for the sirus_devices table.
All queries use prepared statements via $wpdb.
/
final readonly class DeviceRepository implements DeviceRepositoryInterface
{
    private string $table;

    /**
@param \wpdb $wpdb WordPress database abstraction object.

### `findByDeviceId(string $device_id)`

Finds a DeviceRecord by its device_id UUID.
@param string $device_id The device UUID to look up.

### `findByFingerprintHash(string $fingerprint_hash)`

Finds a DeviceRecord by its fingerprint hash.
@param string $fingerprint_hash SHA-256 fingerprint hash to look up.

### `save(DeviceRecord $record)`

Inserts or replaces a DeviceRecord row.
@param DeviceRecord $record The record to persist.

### `updateLastSeen(string $device_id)`

Updates the last_seen timestamp for the given device_id to now.
@param string $device_id The device UUID to update.

### `updateFingerprintHash(string $device_id, string $fingerprint_hash)`

Updates the fingerprint_hash (and last_seen) for the given device_id.
Called when fingerprint drift is detected and the device has been authenticated
via its device_secret. The device_id remains the stable identity; we simply
record the new fingerprint so subsequent visits resolve correctly.
@param string $device_id The device UUID to update.
@param string $fingerprint_hash New SHA-256 fingerprint hash.

### `incrementDriftScore(string $device_id)`

Atomically increments the drift_score and updates last_seen for a device.
Should be called whenever a fingerprint change is detected on an authenticated
device. A high drift_score may indicate a highly mobile device (VPN, carrier
switching) and can inform future trust-level decisions.
@param string $device_id The device UUID to update.

### `rowToRecord(object $row)`

Maps a raw database row object to a DeviceRecord value object.
@param object $row The raw row from wpdb.

