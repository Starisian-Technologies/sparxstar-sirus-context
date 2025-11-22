#!/bin/bash
# Complete cleanup script for SPARXSTAR User Environment Check
# Fixes all PHP, JS, CSS, and HTML formatting issues

set -e

echo "🧹 SPARXSTAR Cleanup Suite"
echo "=========================="
echo ""

# Check if we're in the right directory
if [ ! -f "sparxstar-user-environment-check.php" ]; then
    echo "❌ Error: Must run from project root"
    exit 1
fi

# PHP Fixes
echo "🔧 PHP: Running Rector (structural fixes)..."
if command -v composer &> /dev/null; then
    composer rector:fix || echo "⚠️  Rector completed with warnings"
    echo ""
    
    echo "🔧 PHP: Running PHP CS Fixer (formatting)..."
    composer phpfix || echo "⚠️  PHP CS Fixer completed with warnings"
    echo ""
    
    echo "🔧 PHP: Running PHPCBF (coding standards)..."
    composer phpcbf || echo "⚠️  PHPCBF completed with warnings"
    echo ""
else
    echo "❌ Composer not found, skipping PHP fixes"
fi

# JavaScript/CSS Fixes
echo "🔧 JS: Running ESLint + Prettier..."
if command -v pnpm &> /dev/null; then
    pnpm run fix || echo "⚠️  JS fixes completed with warnings"
    echo ""
else
    echo "❌ pnpm not found, skipping JS/CSS fixes"
fi

# Validation
echo "✅ Cleanup Complete!"
echo ""
echo "To verify fixes, run:"
echo "  composer check    # PHP validation"
echo "  pnpm run lint     # JS/CSS validation"
echo ""
