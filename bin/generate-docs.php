#!/usr/bin/env php
<?php

/**
 * Auto-Documentation Generator for SPARXSTAR User Environment Check
 * Scans /src for classes, extracts PHPDoc + signatures, outputs /docs/php/*.md
 */

declare(strict_types=1);

$srcDir  = __DIR__ . '/../src';
$docsDir = __DIR__ . '/../docs/php';

if ( ! is_dir( $docsDir ) ) {
	mkdir( $docsDir, 0775, true );
}

echo "🔍 Scanning PHP source files in {$srcDir}\n";

$iterator = new RecursiveIteratorIterator(
	new RecursiveDirectoryIterator( $srcDir, RecursiveDirectoryIterator::SKIP_DOTS )
);

$classCount = 0;

foreach ( $iterator as $file ) {
	if ( $file->getExtension() !== 'php' ) {
		continue;
	}

	$contents = file_get_contents( $file->getRealPath() );
	if ( ! $contents ) {
		continue;
	}

	// Class detection
	if ( ! preg_match( '/namespace\s+([^;]+);/', $contents, $ns ) ) {
		continue;
	}

	preg_match( '/(?:final\s+)?(?:abstract\s+)?class\s+([A-Za-z0-9_]+)/', $contents, $cls );

	if ( empty( $cls[1] ) ) {
		continue;
	}

	$namespace = trim( $ns[1] );
	$className = trim( $cls[1] );
	$fullName  = "$namespace\\$className";
	$docFile   = $docsDir . '/' . $className . '.md';

	// Extract class PHPDoc block
	preg_match( '/\/\*\*((?:.|\n)*?)\*\/\s*(?:final\s+)?(?:abstract\s+)?class\s+' . preg_quote( $className, '/' ) . '/m', $contents, $phpDoc );

	$markdown  = "# {$className}\n\n";
	$markdown .= "**Namespace:** `{$namespace}`\n\n";
	$markdown .= "**Full Class Name:** `{$fullName}`\n\n";

	if ( ! empty( $phpDoc[1] ) ) {
		$cleanDoc  = trim(
			preg_replace( '/^\s*\*\s?/m', '', $phpDoc[1] )
		);
		$markdown .= "## Description\n\n" . $cleanDoc . "\n\n";
	}

	// Extract properties
	preg_match_all( '/\/\*\*((?:.|\n)*?)\*\/\s*(?:private|protected|public)\s+(?:static\s+)?(?:readonly\s+)?(?:\??\w+\s+)?\$([A-Za-z0-9_]+)/m', $contents, $properties, PREG_SET_ORDER );

	if ( $properties ) {
		$markdown .= "## Properties\n\n";
		foreach ( $properties as $prop ) {
			$propDoc  = trim( preg_replace( '/^\s*\*\s?/m', '', $prop[1] ) );
			$propName = $prop[2];

			$markdown .= "### `\${$propName}`\n\n";
			$markdown .= $propDoc ? "{$propDoc}\n\n" : "_No documentation_\n\n";
		}
	}

	// Extract methods
	preg_match_all( '/\/\*\*((?:.|\n)*?)\*\/\s*(?:private|protected|public)\s+(?:static\s+)?function\s+([A-Za-z0-9_]+)\s*\((.*?)\)/m', $contents, $methods, PREG_SET_ORDER );

	if ( $methods ) {
		$markdown .= "## Methods\n\n";
		foreach ( $methods as $m ) {
			$methodDoc  = trim( preg_replace( '/^\s*\*\s?/m', '', $m[1] ) );
			$methodName = $m[2];
			$params     = $m[3];

			$markdown .= "### `{$methodName}({$params})`\n\n";
			$markdown .= $methodDoc ? "{$methodDoc}\n\n" : "_No documentation_\n\n";
		}
	}

	file_put_contents( $docFile, $markdown );
	echo "✅ Generated: {$className}.md\n";
	++$classCount;
}

echo "\n🎉 Documentation generation complete! ({$classCount} classes)\n";
echo "📁 Output directory: {$docsDir}\n";
