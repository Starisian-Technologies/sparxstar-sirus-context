# Recorder Event System - Quick Reference

## For Plugin Developers

### How to Log Events

```javascript
// Method 1: Direct call
window.SPARXSTAR.logRecorderEvent({
    type: 'custom_event',
    data: { ... }
});

// Method 2: Using namespace
window.SPARXSTAR.Recorder.logEvent({
    type: 'custom_event',
    data: { ... }
});
```

### Starmus Hooks Integration

Automatic - just install both plugins:

```javascript
// Starmus will automatically trigger:
window.StarmusHooks.addAction('starmus_event', 'UECRecorderMonitor', handler);
```

## Configuration Variables

### JavaScript (Available Globally)

```javascript
window.sparxstarUECRecorderLog = {
    endpoint: "https://yoursite.com/wp-json/star-uec/v1/recorder-log",
    nonce: "abc123..."
};

window.sparxstarUserEnvData = {
    debug: false,  // Set via WP_DEBUG
    // ... other config
};
```

## REST Endpoint

- **URL**: `/wp-json/star-uec/v1/recorder-log`
- **Method**: POST
- **Auth**: X-WP-Nonce header (automatically included)
- **Response**: `{"status":"ok"}` or `{"status":"invalid_json"}`

## Debugging

```bash
# Enable WordPress debug logging
WP_DEBUG=true
WP_DEBUG_LOG=true

# Watch recorder events
tail -f wp-content/debug.log | grep "SparxstarUEC Recorder"
```

## Event Payload

```javascript
{
    type: 'starmus_event',           // Event type
    ts: '2025-11-25T12:34:56.789Z',  // ISO timestamp
    env: { ... },                     // Full SPARXSTAR.State
    network_realtime: { ... },        // Current network status
    event: { ... }                    // Your custom event data
}
```

## Security Notes

- All requests require valid WordPress REST nonce
- Nonce automatically included in requests
- Data sanitized on server before logging
- No authentication required beyond nonce

## Compatibility

- WordPress 5.0+
- Modern browsers (Chrome 39+, Firefox 31+, Safari 11.1+)
- Works with or without Starmus Recorder plugin

## Files Involved

### JavaScript
- `src/js/sparxstar-recorder.js` - Core recorder module
- `src/js/sparxstar-bootstrap.js` - Module loader

### PHP
- `src/api/SparxstarUECRESTController.php` - REST endpoint handler
- `src/core/SparxstarUECAssetManager.php` - Script localization

### Build
- `assets/js/sparxstar-user-environment-check-app.bundle.min.js` - Bundled output

## Common Issues

### Endpoint Not Configured

**Symptom:** Console warning: "Recorder log endpoint not configured"

**Solution:** Ensure scripts are enqueued correctly:
```php
wp_localize_script('handle', 'sparxstarUECRecorderLog', [...]);
```

### Nonce Validation Failed

**Symptom:** 403 Forbidden response

**Solution:** Check nonce is being sent in X-WP-Nonce header

### Events Not Logging

**Symptom:** No logs in debug.log

**Solution:** Enable WP_DEBUG_LOG in wp-config.php:
```php
define('WP_DEBUG_LOG', true);
```

## Testing Checklist

- [ ] Build bundle: `pnpm run build`
- [ ] Validate: `pnpm run validate`
- [ ] Check endpoint available: `/wp-json/star-uec/v1/recorder-log`
- [ ] Test in console: `window.SPARXSTAR.logRecorderEvent({test:true})`
- [ ] Check debug.log for event
- [ ] Verify network request returns 200 OK

## Support

See full documentation in `RECORDER_INTEGRATION.md`
