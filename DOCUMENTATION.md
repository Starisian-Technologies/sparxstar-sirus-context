# Documentation Guide

Complete guide for generating and maintaining documentation for SPARXSTAR User Environment Check.

## Overview

This plugin uses automatic documentation generation for both PHP and JavaScript codebases:

- **PHP**: Extracts PHPDoc blocks from classes, methods, and properties
- **JavaScript**: Converts JSDoc comments to Markdown using jsdoc-to-markdown
- **Output**: Organized Markdown files in `/docs/php` and `/docs/js`

## Quick Start

### Generate All Documentation

```bash
pnpm run docs:all
```

This runs both PHP and JavaScript documentation generators.

### Generate PHP Documentation Only

```bash
composer docs
# or
php bin/generate-docs.php
```

### Generate JavaScript Documentation Only

```bash
pnpm run jsdocs
# or
node bin/generate-js-docs.mjs
```

## Documentation Structure

```
docs/
├── README.md              # Documentation index
├── php/                   # PHP class documentation
│   ├── SparxstarUserEnvironmentCheck.md
│   ├── SparxstarUECAssetManager.md
│   ├── SparxstarUECRESTController.md
│   ├── SparxstarUECDatabase.md
│   └── ...
└── js/                    # JavaScript module documentation
    ├── sparxstar-bootstrap.md
    ├── sparxstar-collector.md
    ├── sparxstar-integrator.md
    └── ...
```

## Writing Documentation

### PHP Docblocks (PHPDoc)

#### Class Documentation

```php
/**
 * Brief description of what the class does.
 *
 * Longer description with more details about the class purpose,
 * architecture decisions, and usage patterns.
 *
 * @package Starisian\SparxstarUEC
 * @version 1.0.0
 */
final class MyClass
{
    // ...
}
```

#### Method Documentation

```php
/**
 * Brief description of the method.
 *
 * Longer description explaining the method's purpose, behavior,
 * and any important implementation details.
 *
 * @param string $param1 Description of the first parameter
 * @param int $param2 Description of the second parameter
 * @param array<string, mixed> $options Optional configuration array
 * @return bool True on success, false on failure
 * @throws InvalidArgumentException When param1 is empty
 */
public function myMethod(string $param1, int $param2, array $options = []): bool
{
    // ...
}
```

#### Property Documentation

```php
/**
 * Brief description of the property.
 *
 * @var string User's session identifier
 */
private string $sessionId;

/**
 * Cache of processed data.
 *
 * @var array<string, mixed>
 */
private array $cache = [];
```

### JavaScript Docblocks (JSDoc)

#### Module Documentation

```javascript
/**
 * @file sparxstar-my-module.js
 * @version 1.0.0
 * @description Brief description of what this module does.
 * 
 * Longer description with architecture notes, dependencies,
 * and usage examples.
 */
```

#### Function Documentation

```javascript
/**
 * Brief description of the function.
 *
 * Longer description explaining the function's purpose, behavior,
 * and any important implementation details.
 *
 * @param {object} options - Configuration options
 * @param {string} options.id - Unique identifier for the instance
 * @param {boolean} [options.debug=false] - Enable debug logging
 * @returns {Promise<object>} Resolved data object with results
 * @throws {Error} When options.id is not provided
 * 
 * @example
 * const result = await myFunction({ id: 'test', debug: true });
 */
async function myFunction(options) {
    // ...
}
```

#### Class/Constructor Documentation

```javascript
/**
 * Represents a data collector instance.
 *
 * @class
 * @param {string} name - Collector name
 * @param {object} config - Configuration object
 */
function Collector(name, config) {
    this.name = name;
    this.config = config;
}

/**
 * Collect data from the environment.
 *
 * @memberof Collector
 * @returns {Promise<object>} Collected data
 */
Collector.prototype.collect = async function() {
    // ...
};
```

#### IIFE Module Pattern

```javascript
/**
 * @namespace SPARXSTAR.MyModule
 * @description Module for handling specific functionality.
 */
(function(window, document) {
    'use strict';

    window.SPARXSTAR = window.SPARXSTAR || {};

    /**
     * Initialize the module.
     *
     * @memberof SPARXSTAR.MyModule
     * @param {object} config - Configuration object
     * @returns {void}
     */
    function init(config) {
        // ...
    }

    window.SPARXSTAR.MyModule = {
        init
    };

})(window, document);
```

## Documentation Generators

### PHP Generator (`bin/generate-docs.php`)

**Features:**

- Recursively scans `src/` directory
- Extracts namespace, class name, PHPDoc blocks
- Parses methods, properties, and parameters
- Outputs one `.md` file per class in `docs/php/`

**Output Format:**

```markdown
# ClassName

Namespace: **Full\Namespace\Path**

## Description

Class description from PHPDoc block

## Properties

### `$propertyName`

Property description

## Methods

### `methodName($param1, $param2)`

Method description with parameters and return values
```

### JavaScript Generator (`bin/generate-js-docs.mjs`)

**Features:**

- Scans `src/js/` directory for `.js` files
- Uses `jsdoc-to-markdown` to parse JSDoc comments
- Outputs one `.md` file per JavaScript file in `docs/js/`
- Supports ES6 modules, classes, and IIFE patterns

**Output Format:**

```markdown
# filename.js

Source: `src/js/filename.js`

## Module: ModuleName

Description of the module

### functionName(param1, param2)

Function description

**Parameters:**
- param1 - Description
- param2 - Description

**Returns:** Description of return value
```

## Best Practices

### 1. Document Public APIs First

Focus on documenting:

- Public methods and properties
- Main entry points
- Configuration options
- Return values and exceptions

### 2. Include Examples

```php
/**
 * Create a new snapshot record.
 *
 * @example
 * $snapshot = create_snapshot([
 *     'visitor_id' => 'abc123',
 *     'device_type' => 'mobile'
 * ]);
 */
```

### 3. Document Complex Logic

```javascript
/**
 * Calculate network bandwidth using the Network Information API.
 *
 * Note: This API is not available in all browsers. Falls back to
 * 'unknown' when unavailable.
 *
 * @returns {string} Bandwidth category: 'slow', 'medium', 'fast', or 'unknown'
 */
```

### 4. Keep Docs in Sync

- Regenerate documentation before releases
- Update docblocks when changing function signatures
- Review generated docs for accuracy

### 5. Use Type Hints

PHP:

```php
/**
 * @param array<string, mixed> $data Associative array of data
 * @return array<int, string> Indexed array of strings
 */
```

JavaScript:

```javascript
/**
 * @param {Array<string>} items - Array of item names
 * @returns {Map<string, object>} Map of item name to item data
 */
```

## Integration with CI/CD

### GitHub Actions Example

Create `.github/workflows/docs.yml`:

```yaml
name: Generate Documentation

on:
  push:
    branches: [main, universe]
  pull_request:

jobs:
  docs:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
      
      - name: Setup Node.js
        uses: actions/setup-node@v3
        with:
          node-version: '20'
      
      - name: Install pnpm
        uses: pnpm/action-setup@v2
        with:
          version: 8.6.0
      
      - name: Install dependencies
        run: |
          composer install --no-dev
          pnpm install
      
      - name: Generate documentation
        run: pnpm run docs:all
      
      - name: Upload docs artifact
        uses: actions/upload-artifact@v3
        with:
          name: documentation
          path: docs/
```

### Auto-commit Documentation

If you want to automatically commit regenerated docs:

```yaml
      - name: Generate documentation
        run: pnpm run docs:all
      
      - name: Commit documentation changes
        uses: stefanzweifel/git-auto-commit-action@v4
        with:
          commit_message: "docs: auto-generate documentation"
          file_pattern: docs/**/*.md
```

## Viewing Documentation

### Locally

Use any Markdown viewer or preview in your IDE:

- **VS Code**: Click on `.md` file and press `Ctrl+Shift+V` (Cmd+Shift+V on Mac)
- **Browser**: Use a markdown viewer extension
- **Command line**: `cat docs/php/ClassName.md`

### GitHub

Documentation files are automatically rendered when browsing the repository on GitHub.

### Static Site

You can use tools like:

- **Docsify** - Convert markdown to a documentation site
- **MkDocs** - Python-based documentation generator
- **VuePress** - Vue-powered static site generator

## Troubleshooting

### "No documentation found" for JavaScript files

**Cause**: Missing or incorrect JSDoc comments

**Solution**: Add proper JSDoc blocks to functions/classes:

```javascript
/**
 * Description of function.
 *
 * @param {string} param - Parameter description
 * @returns {object} Return value description
 */
```

### PHP generator skips classes

**Cause**: Missing namespace declaration or malformed class structure

**Solution**: Ensure file has:

```php
namespace Starisian\SparxstarUEC\MyNamespace;

class MyClass {
    // ...
}
```

### JSDoc fails with syntax errors

**Cause**: Invalid JavaScript syntax preventing parsing

**Solution**: Fix JavaScript syntax errors first, then regenerate

### Outdated documentation

**Cause**: Documentation not regenerated after code changes

**Solution**: Run `pnpm run docs:all` before committing

## Advanced Configuration

### Customize JSDoc Output

Edit `jsdoc.json`:

```json
{
  "opts": {
    "destination": "./docs/js",
    "template": "default",
    "heading-depth": 2
  },
  "plugins": ["plugins/markdown"]
}
```

### Add More PHP Details

Edit `bin/generate-docs.php` to extract additional information:

- Constants
- Traits
- Interfaces
- Return types
- Type hints

### Custom Templates

Create custom Markdown templates for more control over output format.

## Maintenance

### Regular Tasks

- **Weekly**: Review new/changed docblocks in PRs
- **Before Release**: Regenerate all documentation
- **Monthly**: Audit documentation coverage

### Documentation Coverage

Check which files lack documentation:

```bash
# PHP
grep -L "\/\*\*" src/**/*.php

# JavaScript
grep -L "\/\*\*" src/js/*.js
```

## Support

For documentation issues:

1. Check this guide
2. Review example docblocks above
3. Test with `pnpm run docs:all`
4. Verify generated output in `/docs`

See also:

- [PHPDoc Official Documentation](https://docs.phpdoc.org/)
- [JSDoc Official Documentation](https://jsdoc.app/)
- [WordPress Documentation Standards](https://developer.wordpress.org/coding-standards/inline-documentation-standards/)

