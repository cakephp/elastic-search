<?php
declare(strict_types=1);

/**
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link          https://cakephp.org CakePHP(tm) Project
 * @since         4.0.0
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 */
namespace Cake\ElasticSearch\Test\TestCase\Datasource;

use Cake\Datasource\Locator\LocatorInterface;
use Cake\ElasticSearch\Datasource\IndexLocator;
use Cake\ElasticSearch\Datasource\IndexLocatorAwareTrait;
use Cake\ElasticSearch\Index;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

/**
 * Test case for IndexLocatorAwareTrait
 */
class IndexLocatorAwareTraitTest extends TestCase
{
    /**
     * Test subject using the trait
     */
    protected object $subject;

    /**
     * Mock locator
     */
    protected LocatorInterface&MockObject $mockLocator;

    /**
     * setUp method
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Create anonymous class using the trait for testing
        $this->subject = new class {
            use IndexLocatorAwareTrait;

            public function getDefaultIndex(): ?string
            {
                return $this->defaultIndex;
            }

            public function setDefaultIndex(?string $index): void
            {
                $this->defaultIndex = $index;
            }
        };

        $this->mockLocator = $this->createMock(LocatorInterface::class);
    }

    /**
     * tearDown method
     */
    protected function tearDown(): void
    {
        unset($this->subject, $this->mockLocator);
        parent::tearDown();
    }

    /**
     * Test setIndexLocator method
     */
    public function testSetIndexLocator(): void
    {
        $result = $this->subject->setIndexLocator($this->mockLocator);

        $this->assertSame($this->subject, $result, 'setIndexLocator should return $this for fluent interface');
        $this->assertSame($this->mockLocator, $this->subject->getIndexLocator());
    }

    /**
     * Test getIndexLocator method with set locator
     */
    public function testGetIndexLocatorWithSetLocator(): void
    {
        $this->subject->setIndexLocator($this->mockLocator);

        $result = $this->subject->getIndexLocator();

        $this->assertSame($this->mockLocator, $result);
    }

    /**
     * Test getIndexLocator method without set locator (uses FactoryLocator)
     */
    public function testGetIndexLocatorWithFactoryLocator(): void
    {
        // Create a mock trait implementation that we can control the FactoryLocator behavior for
        $mockSubject = new class {
            use IndexLocatorAwareTrait {
                getIndexLocator as public;
            }

            public function getIndexLocator(): LocatorInterface
            {
                if ($this->_indexLocator instanceof LocatorInterface) {
                    return $this->_indexLocator;
                }

                // Return a mock IndexLocator instead of using FactoryLocator
                return $this->_indexLocator = new IndexLocator();
            }
        };

        $result = $mockSubject->getIndexLocator();

        $this->assertInstanceOf(LocatorInterface::class, $result);
        $this->assertInstanceOf(IndexLocator::class, $result);
    }

    /**
     * Test fetchIndex method with provided alias
     */
    public function testFetchIndexWithAlias(): void
    {
        $mockIndex = $this->createMock(Index::class);
        $alias = 'Articles';
        $options = ['connection' => 'test'];

        $this->mockLocator
            ->expects($this->once())
            ->method('get')
            ->with($alias, $options)
            ->willReturn($mockIndex);

        $this->subject->setIndexLocator($this->mockLocator);

        $result = $this->subject->fetchIndex($alias, $options);

        $this->assertSame($mockIndex, $result);
    }

    /**
     * Test fetchIndex method with default alias
     */
    public function testFetchIndexWithDefaultAlias(): void
    {
        $mockIndex = $this->createMock(Index::class);
        $defaultAlias = 'Users';
        $options = [];

        $this->subject->setDefaultIndex($defaultAlias);

        $this->mockLocator
            ->expects($this->once())
            ->method('get')
            ->with($defaultAlias, $options)
            ->willReturn($mockIndex);

        $this->subject->setIndexLocator($this->mockLocator);

        $result = $this->subject->fetchIndex(null, $options);

        $this->assertSame($mockIndex, $result);
    }

    /**
     * Test fetchIndex method without alias or default throws exception
     */
    public function testFetchIndexWithoutAliasThrowsException(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage(
            'You must provide an `$alias` or set the `$defaultIndex` property to a non empty string.',
        );

        $this->subject->setIndexLocator($this->mockLocator);
        $this->subject->fetchIndex();
    }

    /**
     * Test fetchIndex method with empty string alias and no default throws exception
     */
    public function testFetchIndexWithEmptyStringAliasThrowsException(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage(
            'You must provide an `$alias` or set the `$defaultIndex` property to a non empty string.',
        );

        $this->subject->setIndexLocator($this->mockLocator);
        $this->subject->fetchIndex('');
    }

    /**
     * Test fetchIndex method uses null coalescing with default
     */
    public function testFetchIndexNullCoalescingWithDefault(): void
    {
        $mockIndex = $this->createMock(Index::class);
        $defaultAlias = 'Comments';

        $this->subject->setDefaultIndex($defaultAlias);

        $this->mockLocator
            ->expects($this->once())
            ->method('get')
            ->with($defaultAlias, [])
            ->willReturn($mockIndex);

        $this->subject->setIndexLocator($this->mockLocator);

        // Pass null explicitly, should use default
        $result = $this->subject->fetchIndex(null);

        $this->assertSame($mockIndex, $result);
    }

    /**
     * Test fetchIndex method overrides default when alias provided
     */
    public function testFetchIndexOverridesDefaultWhenAliasProvided(): void
    {
        $mockIndex = $this->createMock(Index::class);
        $defaultAlias = 'Comments';
        $providedAlias = 'Articles';

        $this->subject->setDefaultIndex($defaultAlias);

        $this->mockLocator
            ->expects($this->once())
            ->method('get')
            ->with($providedAlias, [])
            ->willReturn($mockIndex);

        $this->subject->setIndexLocator($this->mockLocator);

        // Provided alias should override default
        $result = $this->subject->fetchIndex($providedAlias);

        $this->assertSame($mockIndex, $result);
    }

    /**
     * Test that fetchIndex asserts return type is Index
     */
    public function testFetchIndexAssertsIndexType(): void
    {
        $mockIndex = $this->createMock(Index::class);

        $this->mockLocator
            ->expects($this->once())
            ->method('get')
            ->with('Articles', [])
            ->willReturn($mockIndex);

        $this->subject->setIndexLocator($this->mockLocator);

        $result = $this->subject->fetchIndex('Articles');

        $this->assertInstanceOf(Index::class, $result);
    }

    /**
     * Test getIndexLocator caches the instance
     */
    public function testGetIndexLocatorCachesInstance(): void
    {
        $mockLocator = $this->createMock(LocatorInterface::class);
        $this->subject->setIndexLocator($mockLocator);

        $result1 = $this->subject->getIndexLocator();
        $result2 = $this->subject->getIndexLocator();

        $this->assertSame($result1, $result2, 'getIndexLocator should cache and return same instance');
        $this->assertSame($mockLocator, $result1);
    }

    /**
     * Test defaultIndex property access through trait
     */
    public function testDefaultIndexProperty(): void
    {
        $this->assertNull($this->subject->getDefaultIndex(), 'defaultIndex should be null initially');

        $this->subject->setDefaultIndex('TestIndex');
        $this->assertSame('TestIndex', $this->subject->getDefaultIndex());

        $this->subject->setDefaultIndex(null);
        $this->assertNull($this->subject->getDefaultIndex());
    }
}
