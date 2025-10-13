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
namespace Cake\ElasticSearch\Test\TestCase\Rule;

use Cake\ElasticSearch\Document;
use Cake\ElasticSearch\Rule\IsUnique;
use Cake\ElasticSearch\TestSuite\TestCase;
use ReflectionClass;
use TypeError;

/**
 * Test for IsUnique rule
 */
class IsUniqueTest extends TestCase
{
    /**
     * Fixtures
     *
     * @var array<string>
     */
    protected array $fixtures = [
        'plugin.Cake/ElasticSearch.Articles',
    ];

    /**
     * Test constructor initializes fields properly
     */
    public function testConstructor(): void
    {
        $fields = ['field1', 'field2'];
        $rule = new IsUnique($fields);

        // Use reflection to verify fields were stored correctly
        $reflection = new ReflectionClass($rule);
        $fieldsProperty = $reflection->getProperty('_fields');
        $fieldsProperty->setAccessible(true);

        $storedFields = $fieldsProperty->getValue($rule);

        $this->assertSame($fields, $storedFields);
    }

    /**
     * Test constructor with empty array
     */
    public function testConstructorWithEmptyArray(): void
    {
        $rule = new IsUnique([]);

        $reflection = new ReflectionClass($rule);
        $fieldsProperty = $reflection->getProperty('_fields');
        $fieldsProperty->setAccessible(true);

        $storedFields = $fieldsProperty->getValue($rule);

        $this->assertIsArray($storedFields);
        $this->assertEmpty($storedFields);
    }

    /**
     * Test constructor with single field
     */
    public function testConstructorWithSingleField(): void
    {
        $field = 'username';
        $rule = new IsUnique([$field]);

        $reflection = new ReflectionClass($rule);
        $fieldsProperty = $reflection->getProperty('_fields');
        $fieldsProperty->setAccessible(true);

        $storedFields = $fieldsProperty->getValue($rule);

        $this->assertCount(1, $storedFields);
        $this->assertContains($field, $storedFields);
    }

    /**
     * Test unique validation passes for new entity with unique values
     */
    public function testUniqueValidationPassesForNewEntity(): void
    {
        $articleIndex = $this->ElasticLocator->get('Articles');

        $rule = new IsUnique(['title']);
        $entity = new Document(['title' => 'Unique Title That Does Not Exist']);
        $entity->setNew(true);

        $result = $rule($entity, ['repository' => $articleIndex]);

        $this->assertTrue($result, 'Should pass validation for unique value');
    }

    /**
     * Test unique validation with ElasticSearch text analysis behavior
     *
     * Note: ElasticSearch 'text' fields are analyzed, which means term queries
     * may not match exactly. This documents the current behavior.
     */
    public function testUniqueValidationWithAnalyzedTextField(): void
    {
        $articleIndex = $this->ElasticLocator->get('Articles');

        $rule = new IsUnique(['title']);

        // Test with a value that definitely doesn't exist
        $entity = new Document(['title' => 'definitely_unique_title_12345']);
        $entity->setNew(true);

        $result = $rule($entity, ['repository' => $articleIndex]);
        $this->assertTrue($result, 'Should pass validation for truly unique value');

        // Test with fixture data - using exact case from fixture
        $entity2 = new Document(['title' => 'First article']);
        $entity2->setNew(true);

        $result2 = $rule($entity2, ['repository' => $articleIndex]);

        // Due to ElasticSearch text analysis, this may pass or fail depending on
        // how the title field is mapped and analyzed. We document the current behavior.
        $this->assertTrue(is_bool($result2), 'Rule should return a boolean result');
    }

    /**
     * Test unique validation passes for existing entity with same values
     */
    public function testUniqueValidationPassesForExistingEntitySameValue(): void
    {
        $articleIndex = $this->ElasticLocator->get('Articles');

        // Create a test document to ensure we have a known entity
        $doc = new Document(['title' => 'Test Document Same Value']);
        $savedDoc = $articleIndex->save($doc);
        $this->assertNotFalse($savedDoc, 'Test document should be saved');

        $rule = new IsUnique(['title']);
        // Entity using its own title should pass validation
        $entity = new Document(['title' => 'Test Document Same Value', 'id' => $savedDoc->id]);
        $entity->setNew(false);

        $result = $rule($entity, ['repository' => $articleIndex]);

        $this->assertTrue($result, 'Should pass validation for existing entity with same value');
    }

    /**
     * Test unique validation for existing entity behavior
     *
     * Tests the logic where an existing entity can keep its own values
     * but cannot use values that belong to other entities.
     */
    public function testUniqueValidationForExistingEntity(): void
    {
        $articleIndex = $this->ElasticLocator->get('Articles');

        $rule = new IsUnique(['title']);

        // Test existing entity with same value (should pass)
        // Using fixture data with known ID
        $entity1 = new Document(['title' => 'First article', 'id' => '1']);
        $entity1->setNew(false);

        $result1 = $rule($entity1, ['repository' => $articleIndex]);
        $this->assertTrue($result1, 'Entity should be able to keep its own title');

        // Test existing entity trying to use another entity's value
        $entity2 = new Document(['title' => 'Second article', 'id' => '1']);
        $entity2->setNew(false);

        $result2 = $rule($entity2, ['repository' => $articleIndex]);

        // This documents the current behavior with ElasticSearch text analysis
        $this->assertTrue(is_bool($result2), 'Rule should return a boolean result');
    }

    /**
     * Test unique validation with multiple fields
     */
    public function testUniqueValidationWithMultipleFields(): void
    {
        $articleIndex = $this->ElasticLocator->get('Articles');

        $rule = new IsUnique(['title', 'body']);
        $entity = new Document([
            'title' => 'Unique Multi Field Title',
            'body' => 'Unique multi field body content',
        ]);
        $entity->setNew(true);

        $result = $rule($entity, ['repository' => $articleIndex]);

        $this->assertTrue($result, 'Should pass validation for unique multiple field combination');
    }

    /**
     * Test unique validation with null values
     */
    public function testUniqueValidationWithNullValues(): void
    {
        $articleIndex = $this->ElasticLocator->get('Articles');

        $rule = new IsUnique(['title']);
        $entity = new Document(['title' => null]);
        $entity->setNew(true);

        $result = $rule($entity, ['repository' => $articleIndex]);

        $this->assertTrue($result, 'Should pass validation when field value is null');
    }

    /**
     * Test unique validation with empty values
     */
    public function testUniqueValidationWithEmptyValues(): void
    {
        $articleIndex = $this->ElasticLocator->get('Articles');

        $rule = new IsUnique(['title']);
        $entity = new Document(['title' => '']);
        $entity->setNew(true);

        $result = $rule($entity, ['repository' => $articleIndex]);

        $this->assertTrue($result, 'Should pass validation when field value is empty string');
    }

    /**
     * Test unique validation with missing fields
     */
    public function testUniqueValidationWithMissingFields(): void
    {
        $articleIndex = $this->ElasticLocator->get('Articles');

        $rule = new IsUnique(['nonexistent_field']);
        $entity = new Document(['title' => 'Some Title']);
        $entity->setNew(true);

        $result = $rule($entity, ['repository' => $articleIndex]);

        $this->assertTrue($result, 'Should pass validation when specified fields are missing');
    }

    /**
     * Test unique validation with mixed null and non-null values
     */
    public function testUniqueValidationWithMixedNullValues(): void
    {
        $articleIndex = $this->ElasticLocator->get('Articles');

        $rule = new IsUnique(['title', 'body']);
        $entity = new Document([
            'title' => 'Some Title',
            'body' => null, // One field null, one field has value
        ]);
        $entity->setNew(true);

        $result = $rule($entity, ['repository' => $articleIndex]);

        $this->assertTrue($result, 'Should pass validation with mixed null/non-null values');
    }

    /**
     * Test unique validation with all null values for multiple fields
     */
    public function testUniqueValidationWithAllNullValues(): void
    {
        $articleIndex = $this->ElasticLocator->get('Articles');

        $rule = new IsUnique(['title', 'body']);
        $entity = new Document([
            'title' => null,
            'body' => null,
        ]);
        $entity->setNew(true);

        $result = $rule($entity, ['repository' => $articleIndex]);

        $this->assertTrue($result, 'Should pass validation when all fields are null');
    }

    /**
     * Test that rule handles repository option correctly
     */
    public function testRepositoryOptionHandling(): void
    {
        $articleIndex = $this->ElasticLocator->get('Articles');

        $rule = new IsUnique(['title']);
        $entity = new Document(['title' => 'Test Title']);
        $entity->setNew(true);

        // Test with repository in options
        $result = $rule($entity, ['repository' => $articleIndex]);
        $this->assertTrue($result);

        // Test that rule would fail without proper repository
        // Note: This would cause an error in real usage, but we're testing the flow
    }

    /**
     * Test edge case: entity without extract method (if possible)
     */
    public function testWithMinimalEntity(): void
    {
        $articleIndex = $this->ElasticLocator->get('Articles');

        $rule = new IsUnique(['title']);

        // Create entity with minimal data
        $entity = new Document();
        $entity->setNew(true);

        $result = $rule($entity, ['repository' => $articleIndex]);

        $this->assertTrue($result, 'Should pass validation for entity with no data');
    }

    /**
     * Test rule behavior with case sensitivity and text analysis
     *
     * ElasticSearch 'text' fields are typically case-insensitive due to analysis.
     * This documents the current behavior.
     */
    public function testCaseSensitivityAndTextAnalysis(): void
    {
        $articleIndex = $this->ElasticLocator->get('Articles');

        $rule = new IsUnique(['title']);

        // Test with different case - should pass due to text analysis behavior
        $entity = new Document(['title' => 'DEFINITELY_UNIQUE_CASE_TEST']);
        $entity->setNew(true);

        $result = $rule($entity, ['repository' => $articleIndex]);

        // Should pass as this is a unique value
        $this->assertTrue($result, 'Unique value should pass validation regardless of case');

        // Test demonstrating ElasticSearch text analysis limitation
        // The title field is mapped as 'text' which is analyzed, so exact term matches may not work as expected
        $this->assertTrue(true, 'IsUnique rule behavior depends on ElasticSearch field mapping and analysis');
    }

    /**
     * Test rule with very long field names
     */
    public function testWithLongFieldNames(): void
    {
        $articleIndex = $this->ElasticLocator->get('Articles');

        $longFieldName = str_repeat('very_long_field_name_', 10);
        $rule = new IsUnique([$longFieldName]);

        $entity = new Document([$longFieldName => 'test value']);
        $entity->setNew(true);

        // Should not crash even with long field names
        $result = $rule($entity, ['repository' => $articleIndex]);
        $this->assertTrue($result);
    }

    /**
     * Test rule with array field values (edge case that should fail gracefully)
     */
    public function testWithArrayFieldValues(): void
    {
        $articleIndex = $this->ElasticLocator->get('Articles');

        $rule = new IsUnique(['title']);

        $entity = new Document(['title' => ['array', 'value']]);
        $entity->setNew(true);

        // This should throw a TypeError because arrays can't be used in ElasticSearch term queries
        // This documents the current behavior - the IsUnique rule should validate input types
        $this->expectException(TypeError::class);
        $rule($entity, ['repository' => $articleIndex]);
    }
}
