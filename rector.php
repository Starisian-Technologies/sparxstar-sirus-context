<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Php80\Rector\Class_\ClassPropertyAssignToConstructorPromotionRector;
use Rector\Php81\Rector\Property\ReadOnlyPropertyRector;
use Rector\TypeDeclaration\Rector\ClassMethod\AddVoidReturnTypeWhereNoReturnRector;
use Rector\TypeDeclaration\Rector\ClassMethod\AddMethodCallBasedStrictParamTypeRector;
use Rector\TypeDeclaration\Rector\ClassMethod\ParamTypeByMethodCallTypeRector;
use Rector\TypeDeclaration\Rector\ClassMethod\ReturnTypeFromReturnNewRector;
use Rector\TypeDeclaration\Rector\ClassMethod\ReturnTypeFromStrictTypedCallRector;
use Rector\TypeDeclaration\Rector\Property\TypedPropertyFromStrictConstructorRector;
use Rector\CodeQuality\Rector\Class_\InlineConstructorDefaultToPropertyRector;
use Rector\CodingStyle\Rector\ClassMethod\MakeInheritedMethodVisibilitySameAsParentRector;

return RectorConfig::configure()
	->withPaths(
		array(
			__DIR__ . '/src',
		)
	)
	->withSkip(
		array(
			__DIR__ . '/src/index.php',
			__DIR__ . '/vendor',
		)
	)
	->withPhpSets( php83: true )
	->withPreparedSets(
		deadCode: true,
		codeQuality: true,
		codingStyle: true,
		typeDeclarations: true,
		privatization: true,
		naming: true,
		instanceOf: true,
		earlyReturn: true,
		strictBooleans: true
	)
	->withRules(
		array(
			// Type declarations
			AddVoidReturnTypeWhereNoReturnRector::class,
			ReturnTypeFromReturnNewRector::class,
			ReturnTypeFromStrictTypedCallRector::class,
			TypedPropertyFromStrictConstructorRector::class,
			ParamTypeByMethodCallTypeRector::class,
			AddMethodCallBasedStrictParamTypeRector::class,
		
			// PHP 8.0+ features
			ClassPropertyAssignToConstructorPromotionRector::class,
			InlineConstructorDefaultToPropertyRector::class,
		
			// PHP 8.1+ features (optional - will make properties readonly where possible)
			ReadOnlyPropertyRector::class,
		
			// Coding standards
			MakeInheritedMethodVisibilitySameAsParentRector::class,
		)
	)
	->withImportNames( importShortClasses: false, removeUnusedImports: true );
