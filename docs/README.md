# Documentation

Auto-generated documentation for SPARXSTAR User Environment Check.

## Structure

- **[PHP Documentation](php/)** - Backend classes and services
- **[JavaScript Documentation](js/)** - Frontend collectors and modules

## Generation

### Generate All Documentation

```bash
# PHP + JavaScript
npm run docs:all
```

### Generate PHP Documentation Only

```bash
composer docs
# or
php bin/generate-docs.php
```

### Generate JavaScript Documentation Only

```bash
npm run jsdocs
# or
node bin/generate-js-docs.mjs
```

## PHP Classes

Core plugin classes documented:

- **SparxstarUserEnvironmentCheck** - Main orchestrator
- **SparxstarUECAssetManager** - Asset loading system
- **SparxstarUECRESTController** - REST API endpoint handler
- **SparxstarUECDatabase** - Database operations
- **SparxstarUECGeoIPService** - Geolocation lookups
- **StarUserEnv** - Public API facade
- **StarUserUtils** - Utility functions

## JavaScript Modules

Frontend modules documented:

- **sparxstar-bootstrap** - Entry point and vendor loading
- **sparxstar-state** - Global state management
- **sparxstar-collector** - Data collection pipelines
- **sparxstar-profile** - Device profiling
- **sparxstar-sync** - Server communication
- **sparxstar-ui** - User interface components
- **sparxstar-integrator** - Main orchestrator

## Contributing

When adding new classes or methods, ensure they have proper docblocks:

### PHP Docblocks

```php
/**
 * Brief description of the class/method.
 *
 * Longer description with more details.
 *
 * @param string $param Description of parameter
 * @return bool Description of return value
 * @throws Exception When something goes wrong
 */
```

### JavaScript Docblocks

```javascript
/**
 * Brief description of the function/module.
 *
 * @param {object} options - Configuration options
 * @param {string} options.id - Unique identifier
 * @returns {Promise<object>} Resolved data object
 */
```

Then regenerate docs:

```bash
npm run docs:all
```
