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
 * @since         0.0.1
 * @license       https://www.opensource.org/licenses/mit-license.php MIT License
 */
namespace Cake\ElasticSearch\Test\TestCase\Association;

use Cake\ElasticSearch\Association\Embedded;
use Cake\ElasticSearch\Document;
use Cake\ElasticSearch\Index;
use Cake\ElasticSearch\TestSuite\TestCase;
use ReflectionClass;
use TestApp\Model\Association\ConcreteEmbedded;

/**
 * Tests the Embedded association class
 */
class EmbeddedTest extends TestCase
{
    /**
     * Test constructor with default options
     */
    public function testConstructorWithDefaults(): void
    {
        $embedded = new ConcreteEmbedded('TestAlias');

        $this->assertSame('TestAlias', $embedded->getAlias());
        $this->assertSame('test_alias', $embedded->getProperty());
    }

    /**
     * Test constructor with custom property
     */
    public function testConstructorWithCustomProperty(): void
    {
        $embedded = new ConcreteEmbedded('TestAlias', [
            'property' => 'custom_property',
        ]);

        $this->assertSame('custom_property', $embedded->getProperty());
    }

    /**
     * Test constructor with custom entity class
     */
    public function testConstructorWithCustomEntityClass(): void
    {
        $embedded = new ConcreteEmbedded('TestAlias', [
            'entityClass' => 'CustomEntity',
        ]);

        // Should fallback to Document class since CustomEntity doesn't exist
        $this->assertSame(Document::class, $embedded->getEntityClass());
    }

    /**
     * Test constructor with custom index class
     */
    public function testConstructorWithCustomIndexClass(): void
    {
        $embedded = new ConcreteEmbedded('TestAlias', [
            'indexClass' => 'CustomIndex',
        ]);

        // Should not set since CustomIndex doesn't exist
        $this->assertStringContainsString('Index', $embedded->getIndexClass());
    }

    /**
     * Test setProperty with null value
     */
    public function testSetPropertyWithNull(): void
    {
        $embedded = new ConcreteEmbedded('TestAlias');
        $original = $embedded->getProperty();

        $result = $embedded->setProperty();

        $this->assertSame($embedded, $result); // Test fluent interface
        $this->assertSame($original, $embedded->getProperty()); // Should not change
    }

    /**
     * Test setProperty with string value
     */
    public function testSetPropertyWithString(): void
    {
        $embedded = new ConcreteEmbedded('TestAlias');

        $result = $embedded->setProperty('new_property');

        $this->assertSame($embedded, $result); // Test fluent interface
        $this->assertSame('new_property', $embedded->getProperty());
    }

    /**
     * Test getEntityClass with default fallback
     */
    public function testGetEntityClassDefaultFallback(): void
    {
        $embedded = new ConcreteEmbedded('NonExistentClass');

        $this->assertSame(Document::class, $embedded->getEntityClass());
    }

    /**
     * Test getEntityClass with existing class
     */
    public function testGetEntityClassWithExistingClass(): void
    {
        // Create a mock embedded class that will find a real document class
        $embedded = new ConcreteEmbedded('Address');

        $entityClass = $embedded->getEntityClass();
        $this->assertIsString($entityClass);
        // Should either be Document class or a valid address document class
        $this->assertTrue(
            $entityClass === Document::class ||
            is_subclass_of($entityClass, Document::class) ||
            class_exists($entityClass),
        );
    }

    /**
     * Test setEntityClass with valid class name
     */
    public function testSetEntityClassWithValidClass(): void
    {
        $embedded = new ConcreteEmbedded('TestAlias');

        $result = $embedded->setEntityClass('Document');

        $this->assertSame($embedded, $result); // Test fluent interface
        $this->assertSame(Document::class, $embedded->getEntityClass());
    }

    /**
     * Test setEntityClass with invalid class name
     */
    public function testSetEntityClassWithInvalidClass(): void
    {
        $embedded = new ConcreteEmbedded('TestAlias');

        $result = $embedded->setEntityClass('NonExistentClass');

        $this->assertSame($embedded, $result); // Test fluent interface
        $this->assertSame(Document::class, $embedded->getEntityClass()); // Should fallback
    }

    /**
     * Test getIndexClass with default fallback
     */
    public function testGetIndexClassDefaultFallback(): void
    {
        $embedded = new ConcreteEmbedded('NonExistentIndex');

        $this->assertSame(Index::class, $embedded->getIndexClass());
    }

    /**
     * Test getIndexClass with pluralization
     */
    public function testGetIndexClassWithPluralization(): void
    {
        $embedded = new ConcreteEmbedded('Article');

        $indexClass = $embedded->getIndexClass();
        $this->assertIsString($indexClass);
        // Should try to find ArticlesIndex or fallback to Index
        $this->assertTrue(
            $indexClass === Index::class ||
            is_subclass_of($indexClass, Index::class) ||
            class_exists($indexClass),
        );
    }

    /**
     * Test setIndexClass with Index instance
     */
    public function testSetIndexClassWithIndexInstance(): void
    {
        $embedded = new ConcreteEmbedded('TestAlias');
        $index = $this->getMockBuilder(Index::class)
            ->disableOriginalConstructor()
            ->getMock();

        $result = $embedded->setIndexClass($index);

        $this->assertSame($embedded, $result); // Test fluent interface
        $this->assertSame(get_class($index), $embedded->getIndexClass());
    }

    /**
     * Test setIndexClass with string class name
     */
    public function testSetIndexClassWithStringClassName(): void
    {
        $embedded = new ConcreteEmbedded('TestAlias');

        $result = $embedded->setIndexClass('Index');

        $this->assertSame($embedded, $result); // Test fluent interface
        $this->assertSame(Index::class, $embedded->getIndexClass());
    }

    /**
     * Test setIndexClass with invalid class name
     */
    public function testSetIndexClassWithInvalidClassName(): void
    {
        $embedded = new ConcreteEmbedded('TestAlias');
        $originalClass = $embedded->getIndexClass();

        $result = $embedded->setIndexClass('NonExistentIndexClass');

        $this->assertSame($embedded, $result); // Test fluent interface
        // Should not change the class since it doesn't exist
        $this->assertSame($originalClass, $embedded->getIndexClass());
    }

    /**
     * Test setIndexClass with null value
     */
    public function testSetIndexClassWithNull(): void
    {
        $embedded = new ConcreteEmbedded('TestAlias');
        $originalClass = $embedded->getIndexClass();

        $result = $embedded->setIndexClass(null);

        $this->assertSame($embedded, $result); // Test fluent interface
        $this->assertSame($originalClass, $embedded->getIndexClass()); // Should not change
    }

    /**
     * Test getAlias method
     */
    public function testGetAlias(): void
    {
        $embedded = new ConcreteEmbedded('MyTestAlias');

        $this->assertSame('MyTestAlias', $embedded->getAlias());
    }

    /**
     * Test property caching behavior
     */
    public function testPropertyCaching(): void
    {
        $embedded = new ConcreteEmbedded('TestCache');

        // First call should set the property
        $property1 = $embedded->getProperty();
        $this->assertSame('test_cache', $property1);

        // Second call should return cached value
        $property2 = $embedded->getProperty();
        $this->assertSame($property1, $property2);

        // After setting a new property, getter should return new value
        $embedded->setProperty('new_cached_property');
        $property3 = $embedded->getProperty();
        $this->assertSame('new_cached_property', $property3);
    }

    /**
     * Test entity class caching behavior
     */
    public function testEntityClassCaching(): void
    {
        $embedded = new ConcreteEmbedded('TestEntityCache');

        // First call should resolve and cache the class
        $class1 = $embedded->getEntityClass();
        $this->assertIsString($class1);

        // Second call should return cached value
        $class2 = $embedded->getEntityClass();
        $this->assertSame($class1, $class2);

        // After setting a new class, getter should return new value
        $embedded->setEntityClass('Document');
        $class3 = $embedded->getEntityClass();
        $this->assertSame(Document::class, $class3);
    }

    /**
     * Test index class caching behavior
     */
    public function testIndexClassCaching(): void
    {
        $embedded = new ConcreteEmbedded('TestIndexCache');

        // First call should resolve and cache the class
        $class1 = $embedded->getIndexClass();
        $this->assertIsString($class1);

        // Second call should return cached value
        $class2 = $embedded->getIndexClass();
        $this->assertSame($class1, $class2);

        // After setting a new class, getter should return new value
        $embedded->setIndexClass('Index');
        $class3 = $embedded->getIndexClass();
        $this->assertSame(Index::class, $class3);
    }

    /**
     * Test constants are defined
     */
    public function testConstants(): void
    {
        $this->assertSame('oneToOne', Embedded::ONE_TO_ONE);
        $this->assertSame('oneToMany', Embedded::ONE_TO_MANY);
    }

    /**
     * Test getEntityClass edge case with complex namespace resolution
     */
    public function testGetEntityClassComplexNamespaceResolution(): void
    {
        // Create an embedded class that mimics real namespace structure
        $embedded = new class ('ComplexTest') extends Embedded {
            public function hydrate(array $data, array $options): Document
            {
                return new Document($data);
            }

            public function type(): string
            {
                return self::ONE_TO_ONE;
            }
        };

        $entityClass = $embedded->getEntityClass();
        $this->assertSame(Document::class, $entityClass);
    }

    /**
     * Test abstract methods are properly declared
     */
    public function testAbstractMethodsExist(): void
    {
        $reflection = new ReflectionClass(Embedded::class);

        $this->assertTrue($reflection->isAbstract());

        $hydrateMethod = $reflection->getMethod('hydrate');
        $this->assertTrue($hydrateMethod->isAbstract());

        $typeMethod = $reflection->getMethod('type');
        $this->assertTrue($typeMethod->isAbstract());
    }
}
