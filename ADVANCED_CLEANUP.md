# Advanced Cleanup Tools Summary

## ✅ Complete Cleanup Suite Installed

All advanced code quality and formatting tools are now configured and ready to use.

## 🛠️ Tools Installed

### PHP Tools

1. **Rector** (`rector/rector` v2.2.8)
   - Structural PHP refactoring
   - Auto-upgrades to PHP 8.3 syntax
   - Constructor property promotion
   - Readonly properties
   - Type declarations
   - Dead code removal

2. **PHP CS Fixer** (`friendsofphp/php-cs-fixer`)
   - PSR-12 compliance
   - Advanced formatting rules
   - Array syntax normalization
   - Import ordering by length
   - PHPDoc alignment and cleanup

3. **PHPCS/PHPCBF** (already installed)
   - WordPress Coding Standards
   - Auto-fix violations

4. **PHPStan** (already installed)
   - Static analysis
   - Type checking

### JavaScript Tools

1. **ESLint** (v9.39.1)
   - Code quality checks
   - ES2022 support
   - Import validation
   - Prettier integration

2. **Prettier** (v3.6.2)
   - Consistent code formatting
   - Single quotes
   - 100-char line width
   - ES5 trailing commas

### CSS Tools

1. **Stylelint** (v16.25.0)
   - CSS best practices
   - Standard config
   - Tab indentation
   - Auto-fix support

## 🚀 Quick Commands

### Fix Everything at Once

```bash
./cleanup.sh
```

This runs in order:

1. Rector (structural PHP fixes)
2. PHP CS Fixer (formatting)
3. PHPCBF (WordPress standards)
4. ESLint + Prettier (JavaScript)
5. Stylelint (CSS)

### Individual PHP Commands

```bash
composer rector:fix      # Structural refactoring
composer phpfix          # Formatting fixes
composer phpcbf          # WordPress standards
composer rector          # Dry-run (check only)
composer phpfix:dry      # Dry-run (check only)
```

### Individual JavaScript/CSS Commands

```bash
pnpm run fix             # Fix all JS/CSS
pnpm run lint:fix        # ESLint auto-fix
pnpm run stylelint:fix   # Stylelint auto-fix
pnpm run format          # Prettier formatting
pnpm run lint            # Check only (no changes)
```

### Validation Commands

```bash
composer check           # PHP validation (PHPCS + PHPStan)
pnpm run lint            # JS/CSS validation
```

## 📋 Configuration Files

| File | Purpose |
|------|---------|
| `rector.php` | Rector structural refactoring rules (PHP 8.3) |
| `.php-cs-fixer.dist.php` | PHP CS Fixer formatting rules (PSR-12+) |
| `phpcs.xml.dist` | PHPCS/PHPCBF WordPress standards |
| `phpstan.neon.dist` | PHPStan static analysis |
| `.eslintrc.json` | ESLint JavaScript rules (ES2022) |
| `.prettierrc` | Prettier formatting options |
| `.stylelintrc.json` | Stylelint CSS rules |
| `.editorconfig` | Editor-agnostic formatting |
| `cleanup.sh` | One-command cleanup script |

## 🤖 GitHub Actions Automation

### Workflow: `.github/workflows/code-quality.yml`

**Triggers:**

- Push to `universe` or `main` branches
- Pull requests to `universe` or `main`

**Jobs:**

1. **php-quality** - Runs PHPCS, PHPStan, Rector (check-only)
2. **js-quality** - Runs ESLint, Stylelint (check-only)
3. **auto-fix** - Automatically fixes code and commits (universe branch only)

**Auto-Fix Features:**

- Runs all cleanup tools
- Commits fixes back to the repository
- Uses `[skip ci]` to prevent infinite loops
- Only runs on direct pushes to `universe` branch

## 📝 What Each Tool Fixes

### Rector

- ✅ PHP 8.0+ constructor property promotion
- ✅ PHP 8.1+ readonly properties
- ✅ Type declarations on methods and properties
- ✅ Return type declarations
- ✅ Dead code removal
- ✅ Early returns
- ✅ Strict boolean checks
- ✅ Code quality improvements

### PHP CS Fixer

- ✅ PSR-12 compliance
- ✅ Short array syntax (`[]` instead of `array()`)
- ✅ Single quotes instead of double quotes
- ✅ Unused import removal
- ✅ Import ordering by length
- ✅ Binary operator spacing
- ✅ PHPDoc alignment and formatting
- ✅ Trailing whitespace removal

### ESLint + Prettier

- ✅ Unused variable warnings
- ✅ Undefined variable errors
- ✅ Import/export validation
- ✅ Consistent code formatting
- ✅ Single quotes
- ✅ 100-character line width
- ✅ Proper semicolon usage

### Stylelint

- ✅ CSS best practices
- ✅ Consistent formatting
- ✅ Tab indentation
- ✅ Property ordering
- ✅ Selector validation

## 🔄 Recommended Workflow

### Before Committing

```bash
./cleanup.sh             # Fix all issues
composer check           # Validate PHP
pnpm run lint            # Validate JS/CSS
git add .
git commit -m "your message"
```

### During Development

```bash
pnpm run build:dev       # Fast development build
pnpm run fix             # Fix JS/CSS as you code
```

### Before Release

```bash
./cleanup.sh             # Full cleanup
pnpm run docs:all        # Generate documentation
pnpm run build           # Production build
composer test            # Run all tests
```

## 🎯 IDE Integration

### VS Code Extensions

Install these for automatic formatting on save:

- **PHP Intelephense** - PHP language support
- **PHP CS Fixer** - Format PHP on save
- **ESLint** - JavaScript linting
- **Prettier** - Code formatter
- **Stylelint** - CSS linting
- **EditorConfig** - Consistent formatting

### VS Code Settings

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

## 🐛 Troubleshooting

### "Rector out of memory"

Increase PHP memory limit:

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

### "pnpm: command not found"

Ensure pnpm is in your PATH or install globally:

```bash
npm install -g pnpm@8.6.0
```

### GitHub Actions auto-fix not working

Ensure:

1. Workflow has write permissions to repository
2. Changes are being made to files in `src/`
3. The branch is `universe` (auto-fix only runs there)

## 📊 Tool Execution Order

The cleanup script runs tools in this order for optimal results:

1. **Rector** - Structural changes first (affects code structure)
2. **PHP CS Fixer** - Formatting after structural changes
3. **PHPCBF** - WordPress standards last (preserves previous fixes)
4. **ESLint + Prettier** - JavaScript formatting
5. **Stylelint** - CSS formatting

This order prevents tools from conflicting with each other.

## 🎉 Summary

You now have a complete, professional-grade code quality suite with:

- ✅ 4 PHP tools (Rector, PHP CS Fixer, PHPCBF, PHPStan)
- ✅ 3 JavaScript/CSS tools (ESLint, Prettier, Stylelint)
- ✅ One-command cleanup script
- ✅ GitHub Actions automation
- ✅ IDE integration support
- ✅ Comprehensive documentation

Run `./cleanup.sh` to fix all code quality issues across PHP, JavaScript, and CSS!
