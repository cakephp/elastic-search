<?php
declare(strict_types=1);

use Rector\CodeQuality\Rector\If_\SimplifyIfElseToTernaryRector;
use Rector\CodingStyle\Rector\ClassMethod\MakeInheritedMethodVisibilitySameAsParentRector;
use Rector\Config\RectorConfig;
use Rector\Strict\Rector\Empty_\DisallowedEmptyRuleFixerRector;
use Rector\ValueObject\PhpVersion;

return RectorConfig::configure()
    ->withPhpVersion(PhpVersion::PHP_83)
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->withSkip([
        // CakePHP coding standards compatibility
        DisallowedEmptyRuleFixerRector::class,
        SimplifyIfElseToTernaryRector::class,
        // Skip visibility changes that might break inheritance
        MakeInheritedMethodVisibilitySameAsParentRector::class,
    ])
    ->withParallel()
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        codingStyle: true,
        //naming: true,
        //typeDeclarations: true, // Disabled due to conflicts with CakePHP coding standards
    );
