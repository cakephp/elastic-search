<?php
declare(strict_types=1);

use Rector\CodingStyle\Rector\ClassMethod\MakeInheritedMethodVisibilitySameAsParentRector;
use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\ClassMethod\RemoveDuplicatedReturnSelfDocblockRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveUselessReturnTagRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveUselessUnionReturnDocblockRector;
use Rector\ValueObject\PhpVersion;

return RectorConfig::configure()
    ->withPhpVersion(PhpVersion::PHP_84)
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->withSkip([
        // Skip visibility changes that might break inheritance
        MakeInheritedMethodVisibilitySameAsParentRector::class,
        // Docblock removal rules added in rector 2.5/2.6. They are skipped to keep
        // the diff behavior-neutral, and because `@return $this` is still needed for
        // PHPStan to track the fluent interfaces declared by CakePHP interfaces.
        RemoveDuplicatedReturnSelfDocblockRector::class,
        RemoveUselessReturnTagRector::class,
        RemoveUselessUnionReturnDocblockRector::class,
    ])
    ->withParallel()
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        codingStyle: true,
        //naming: true,
        //typeDeclarations: true, // Disabled due to conflicts with CakePHP coding standards
    );
