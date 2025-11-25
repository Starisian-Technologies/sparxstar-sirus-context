# External Plugin Event Recorder Integration

## Overview

The SPARXSTAR User Environment Check plugin now includes a **Recorder Event System** that allows external plugins (like Starmus Recorder or other monitoring tools) to log events and errors with full environment context.

## Architecture

### Components

1. **JavaScript Module**: `src/js/sparxstar-recorder.js`
   - Listens for events from external plugins
   - Captures environment snapshot at time of event
   - Sends events to WordPress REST endpoint

2. **REST Endpoint**: `/wp-json/star-uec/v1/recorder-log`
   - Receives event payloads from frontend
   - Validates and sanitizes data
   - Logs to WordPress debug log if enabled

3. **Asset Localization**: `sparxstarUECRecorderLog`
   - Provides endpoint URL and nonce to JavaScript
   - Configured in `SparxstarUECAssetManager`

## JavaScript API

### Global Function

```javascript
window.SPARXSTAR.logRecorderEvent(eventData)
```

**Parameters:**
- `eventData` (Object): Event data from external plugin

**Example Usage:**
```javascript
// From an external plugin
window.SPARXSTAR.logRecorderEvent({
    error_type: 'javascript_error',
    message: 'Uncaught TypeError',
    stack: error.stack,
    source: 'my-plugin'
});
```

### Automatic Starmus Integration

The recorder automatically attaches to `window.StarmusHooks` if available:

```javascript
// Automatic - no configuration needed
window.StarmusHooks.addAction('starmus_event', 'UECRecorderMonitor', function(data) {
    window.SPARXSTAR.logRecorderEvent(data);
});
```

**Retry Logic:**
- If `StarmusHooks` is not yet loaded, retries every 500ms
- No manual initialization required

## Event Payload Structure

Each logged event includes:

```javascript
{
    type: 'starmus_event',
    ts: '2025-11-25T12:34:56.789Z',
    env: {
        // Full SPARXSTAR.State snapshot
        technical: { ... },
        identifiers: { ... },
        privacy: { ... }
    },
    network_realtime: {
        onLine: true,
        effectiveType: '4g',
        rtt: 50,
        saveData: false
    },
    event: {
        // Original event data from external plugin
    }
}
```

## PHP REST Endpoint

### Endpoint Details

- **URL**: `/wp-json/star-uec/v1/recorder-log`
- **Method**: `POST`
- **Authentication**: WordPress REST nonce required
- **Controller**: `SparxstarUECRESTController::handle_recorder_log()`

### Request Headers

```http
POST /wp-json/star-uec/v1/recorder-log
Content-Type: application/json
X-WP-Nonce: {nonce_value}
```

### Response

**Success (200):**
```json
{
    "status": "ok"
}
```

**Error (400):**
```json
{
    "status": "invalid_json"
}
```

**Error (403):**
```json
{
    "code": "invalid_nonce",
    "message": "Invalid security token.",
    "data": {
        "status": 403
    }
}
```

## Logging Behavior

### Debug Mode

When `WP_DEBUG_LOG` is enabled, all recorder events are logged:

```php
// wp-content/debug.log
[SparxstarUEC Recorder] {"type":"starmus_event","ts":"2025-11-25T12:34:56.789Z",...}
```

### StarLogger Integration

Events are also logged via `StarLogger` for consistency:

```php
StarLogger::info('RecorderEvent', 'External plugin event received', [
    'event_type' => 'starmus_event',
    'timestamp' => '2025-11-25T12:34:56.789Z',
    'has_env_data' => true,
    'event_data' => [...]
]);
```

## Integration Examples

### Example 1: Error Monitoring Plugin

```javascript
// In your error monitoring plugin
window.addEventListener('error', function(event) {
    if (window.SPARXSTAR && window.SPARXSTAR.logRecorderEvent) {
        window.SPARXSTAR.logRecorderEvent({
            type: 'javascript_error',
            message: event.message,
            filename: event.filename,
            lineno: event.lineno,
            colno: event.colno,
            error: event.error ? event.error.stack : null
        });
    }
});
```

### Example 2: Performance Monitoring

```javascript
// Log performance metrics
if (window.SPARXSTAR && window.SPARXSTAR.logRecorderEvent) {
    window.SPARXSTAR.logRecorderEvent({
        type: 'performance_metric',
        metric: 'LCP',
        value: 2500,
        rating: 'good'
    });
}
```

### Example 3: Custom Plugin Events

```javascript
// Your custom plugin
function logCustomEvent(eventName, eventData) {
    if (window.SPARXSTAR && window.SPARXSTAR.Recorder) {
        window.SPARXSTAR.Recorder.logEvent({
            plugin: 'my-custom-plugin',
            event_name: eventName,
            data: eventData
        });
    }
}
```

## Transport Methods

### Preferred: sendBeacon

For reliability during page unload:

```javascript
navigator.sendBeacon(endpoint, blob);
```

**Advantages:**
- Survives page navigation
- Non-blocking
- Guaranteed delivery attempt

### Fallback: fetch with keepalive

```javascript
fetch(endpoint, {
    method: 'POST',
    body: JSON.stringify(payload),
    keepalive: true
});
```

**Used when:**
- `sendBeacon` is not available
- Older browsers

## Security

### Nonce Validation

All requests require valid WordPress REST nonce:

```javascript
// Automatically included in requests
'X-WP-Nonce': window.sparxstarUECRecorderLog.nonce
```

### Permission Callback

```php
public function check_permissions(WP_REST_Request $request): bool|WP_Error
{
    $nonce = $request->get_header('X-WP-Nonce');
    if (!$nonce || !wp_verify_nonce($nonce, 'wp_rest')) {
        return new WP_Error('invalid_nonce', 'Invalid security token.', ['status' => 403]);
    }
    return true;
}
```

### Data Sanitization

All event data is sanitized before logging:

```php
$data = json_decode($body, true);
if (!is_array($data)) {
    return new WP_REST_Response(['status' => 'invalid_json'], 400);
}
```

## Configuration

### PHP Localization

The endpoint and nonce are automatically provided:

```php
// In SparxstarUECAssetManager::enqueue_frontend()
wp_localize_script(
    'sparxstar-user-environment-check-app',
    'sparxstarUECRecorderLog',
    [
        'endpoint' => esc_url_raw(rest_url('star-uec/v1/recorder-log')),
        'nonce'    => wp_create_nonce('wp_rest'),
    ]
);
```

### JavaScript Access

```javascript
// Available globally
window.sparxstarUECRecorderLog = {
    endpoint: "https://example.com/wp-json/star-uec/v1/recorder-log",
    nonce: "abc123..."
};
```

## Error Handling

### JavaScript Errors

All errors are caught and logged to console:

```javascript
try {
    // ... recorder logic
} catch (e) {
    console.warn('[SparxstarUEC] logRecorderEvent failed:', e);
}
```

**Behavior:**
- Never breaks the page
- Logs warnings for debugging
- Continues execution

### Network Errors

Fetch failures are silently ignored:

```javascript
fetch(endpoint, options).catch(() => {
    // Silent fail - don't break the page
});
```

## Debugging

### Enable Debug Mode

```php
// wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

### Check Logs

```bash
# View recorder events
tail -f wp-content/debug.log | grep "SparxstarUEC Recorder"
```

### Browser Console

When `sparxstarUserEnvData.debug` is true:

```javascript
[SparxstarUEC] Starmus event listener attached successfully.
[SparxstarUEC] Recorder log endpoint not configured. // If endpoint missing
[SparxstarUEC] logRecorderEvent failed: Error... // On errors
```

## Testing

### Manual Test

```javascript
// In browser console
window.SPARXSTAR.logRecorderEvent({
    test: true,
    message: 'Testing recorder system',
    timestamp: new Date().toISOString()
});
```

### Expected Output

**Browser Network Tab:**
- POST to `/wp-json/star-uec/v1/recorder-log`
- Status: 200 OK
- Response: `{"status":"ok"}`

**WordPress Debug Log:**
```
[SparxstarUEC Recorder] {"type":"starmus_event","ts":"...","event":{"test":true,...}}
```

## Performance Considerations

### Non-Blocking

- Uses `sendBeacon` (asynchronous)
- Fallback uses `keepalive: true`
- Never blocks main thread

### Bandwidth

- Minimal payload (JSON)
- No retries on failure
- Single request per event

### Memory

- No event queuing
- Immediate transmission
- Automatic cleanup

## Compatibility

### Browser Support

- **sendBeacon**: Chrome 39+, Firefox 31+, Safari 11.1+
- **fetch**: All modern browsers
- **Fallback**: Graceful degradation

### WordPress Version

- Requires: WordPress 5.0+
- REST API support required

### External Plugins

Compatible with any plugin that:
- Uses `window.StarmusHooks.addAction()`
- Or calls `window.SPARXSTAR.logRecorderEvent()` directly

## Future Enhancements

Potential additions:

1. **Database Storage**: Store events in custom table for admin review
2. **Event Filtering**: Filter which event types to log
3. **Rate Limiting**: Prevent event spam
4. **Aggregation**: Batch multiple events
5. **Analytics Dashboard**: View events in WordPress admin

## Files Modified

1. `src/js/sparxstar-recorder.js` - New recorder module
2. `src/js/sparxstar-bootstrap.js` - Added recorder import
3. `src/api/SparxstarUECRESTController.php` - Added recorder endpoint
4. `src/core/SparxstarUECAssetManager.php` - Added localization
5. `validate-build.cjs` - Added recorder validation

## Summary

The Recorder Event System provides a robust, secure, and performant way for external plugins to log events with full environmental context. It integrates seamlessly with existing plugins like Starmus Recorder while maintaining the plugin's architectural principles of single responsibility and clean separation of concerns.
