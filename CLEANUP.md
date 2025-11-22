# Cleanup Suite Documentation

## Overview

Complete code quality and formatting suite for PHP, JavaScript, and CSS.

## Quick Commands

### Fix Everything

```bash
./cleanup.sh              # One-command fix for all code
```

### Generate Documentation

```bash
pnpm run docs:all         # Generate PHP + JavaScript docs
composer docs             # PHP documentation only
pnpm run jsdocs           # JavaScript documentation only
```

### PHP Fixes

```bash
composer rector:fix       # Structural refactoring (PHP 8.x upgrades)
composer phpfix           # Formatting & code style
composer phpcbf           # WordPress coding standards
composer fix              # All PHP fixes combined
```

### JavaScript/CSS Fixes

```bash
pnpm run fix              # ESLint + Stylelint + Prettier
pnpm run lint:fix         # ESLint auto-fix
pnpm run stylelint:fix    # Stylelint auto-fix
pnpm run format           # Prettier formatting
```

### Validation (Check Only - No Changes)

```bash
composer check            # PHP validation
pnpm run lint             # JS/CSS validation
composer rector           # Rector dry-run
composer phpfix:dry       # PHP CS Fixer dry-run
```

## Tools Included

### Documentation

- **PHP Documentation Generator** - Auto-generates Markdown docs from PHPDoc blocks
- **JSDoc to Markdown** - Converts JSDoc comments to Markdown documentation
- **Unified Docs** - Combined PHP + JavaScript documentation in `/docs`

### PHP

- **Rector** - Structural refactoring, PHP version upgrades
- **PHP CS Fixer** - Advanced formatting (PSR-12+)
- **PHPCBF** - WordPress Coding Standards auto-fixer
- **PHPStan** - Static analysis

### JavaScript

- **ESLint** - Code quality & error detection
- **Prettier** - Consistent formatting
- **Plugin: import** - ES6 import validation

### CSS

- **Stylelint** - CSS linting
- **Stylelint Standard Config** - Best practices
- **Prettier** - Consistent formatting

## Configuration Files

| File | Purpose |
|------|---------|
| `bin/generate-docs.php` | PHP documentation generator |
| `bin/generate-js-docs.mjs` | JavaScript documentation generator |
| `jsdoc.json` | JSDoc configuration |
| `rector.php` | Rector structural refactoring rules |
| `.php-cs-fixer.dist.php` | PHP CS Fixer formatting rules |
| `phpcs.xml.dist` | PHPCS/PHPCBF WordPress standards |
| `.eslintrc.json` | ESLint JavaScript rules |
| `.prettierrc` | Prettier formatting options |
| `.stylelintrc.json` | Stylelint CSS rules |
| `.editorconfig` | Editor-agnostic formatting |
| `cleanup.sh` | One-command cleanup script |

## Typical Workflow

### Before Committing

```bash
./cleanup.sh              # Fix all issues
composer check            # Validate PHP
pnpm run lint             # Validate JS/CSS
```

### During Development

```bash
pnpm run build:dev        # Fast build without minification
pnpm run fix              # Fix JS/CSS as you code
```

### Before Release

```bash
./cleanup.sh              # Full cleanup
pnpm run docs:all         # Generate documentation
pnpm run build            # Production build
composer test             # Run all tests
```

## IDE Integration

### VS Code

Install these extensions:

- **PHP Intelephense** - PHP language support
- **PHP CS Fixer** - Auto-format on save
- **ESLint** - JavaScript linting
- **Prettier** - Code formatter
- **Stylelint** - CSS linting
- **EditorConfig** - Consistent formatting

Add to `.vscode/settings.json`:

```json
{
  "editor.formatOnSave": true,
  "editor.codeActionsOnSave": {
    "source.fixAll.eslint": true,
    "source.fixAll.stylelint": true
  },
  "[php]": {
    "editor.defaultFormatter": "junstyle.php-cs-fixer"
  },
  "[javascript]": {
    "editor.defaultFormatter": "esbenp.prettier-vscode"
  },
  "[css]": {
    "editor.defaultFormatter": "esbenp.prettier-vscode"
  }
}
```

## Troubleshooting

### "composer: command not found"

Install Composer: <https://getcomposer.org/>

### "pnpm: command not found"

```bash
npm install -g pnpm
```

### "Rector out of memory"

Increase PHP memory:

```bash
php -d memory_limit=2G vendor/bin/rector process
```

### "PHPStan errors after Rector"

Run fixes in order:

```bash
composer rector:fix
composer phpfix
composer phpcbf
composer phpstan
```

## What Each Tool Fixes

### Rector

- Upgrades to PHP 8.x syntax
- Constructor property promotion
- Readonly properties
- Type declarations
- Return types
- Dead code removal

### PHP CS Fixer

- PSR-12 compliance
- Array syntax (short arrays)
- Import ordering (by length)
- Unused imports removal
- Trailing whitespace
- PHPDoc formatting

### ESLint

- Unused variables
- Undefined variables
- Import errors
- Code quality issues

### Prettier

- Consistent indentation
- Quote style (single quotes)
- Line width (100 chars)
- Trailing commas

### Stylelint

- CSS best practices
- Consistent formatting
- Property ordering
- Selector validation

## CI/CD Integration

Add to GitHub Actions (`.github/workflows/quality.yml`):

```yaml
name: Code Quality

on: [push, pull_request]

jobs:
  php:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - uses: php-actions/composer@v6
      - run: composer check

  javascript:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - uses: pnpm/action-setup@v2
      - run: pnpm install
      - run: pnpm run lint
```

## Advanced Usage

### Fix Only Specific Files

```bash
# PHP
vendor/bin/php-cs-fixer fix src/core/SparxstarUECAssetManager.php

# JavaScript
pnpm eslint src/js/sparxstar-sync.js --fix

# CSS
pnpm stylelint src/css/sparxstar-user-environment-check.css --fix
```

### Generate Reports

```bash
# PHP CS Fixer report
vendor/bin/php-cs-fixer fix --dry-run --diff

# ESLint report
pnpm eslint src/js/ --format html --output-file eslint-report.html

# Stylelint report
pnpm stylelint src/css/ --formatter verbose
```

## Maintenance

### Update Tools

```bash
composer update --dev      # Update PHP tools
pnpm update --dev          # Update JS/CSS tools
```

### Add New Rules

1. Edit configuration files (`.php-cs-fixer.dist.php`, `.eslintrc.json`, etc.)
2. Test with dry-run: `composer phpfix:dry` or `pnpm run lint`
3. Apply: `./cleanup.sh`
4. Commit configuration changes

## Support

For issues with the cleanup suite:

1. Check tool documentation
2. Review configuration files
3. Run tools individually to isolate issues
4. Check this documentation's troubleshooting section
