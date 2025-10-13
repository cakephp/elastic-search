<?php
declare(strict_types=1);

namespace Cake\ElasticSearch\Test\TestCase;

use Cake\Datasource\ConnectionManager;
use Cake\ElasticSearch\Datasource\Connection;
use Cake\ElasticSearch\Document;
use Cake\ElasticSearch\Index;
use Cake\ElasticSearch\Rule\IsUnique;
use Cake\ElasticSearch\TestSuite\TestCase;

class RulesCheckerTest extends TestCase
{
    public array $fixtures = ['plugin.Cake/ElasticSearch.Articles'];

    protected Connection $connection;

    protected Index $index;

    protected function setUp(): void
    {
        parent::setUp();
        /** @var Connection $connection */
        $this->connection = ConnectionManager::get('test');
        $this->index = new Index(
            [
                'name' => 'articles',
                'connection' => $this->connection,
            ],
        );
    }

    /**
     * Tests the isUnique domain rule
     *
     * @group save
     */
    public function testIsUniqueDomainRule(): void
    {
        $document = new Document(
            [
                'user_id' => 1,
            ],
        );

        $rules = $this->index->rulesChecker();
        $rules->add(
            new IsUnique([ 'user_id' ]),
            '_isUnique',
            [
                'errorField' => 'user_id',
                'message' => 'This value is already in use',
            ],
        );

        $this->assertFalse($this->index->save($document));
        $this->assertEquals(['_isUnique' => 'This value is already in use'], $document->getError('user_id'));
    }

    /**
     * Test unique rule on existing document
     *
     * @group save
     */
    public function testIsUniqueExisting(): void
    {
        $document = $this->index->get(1);
        $rules = $this->index->rulesChecker();
        $rules->add(new IsUnique([ 'user_id' ]));

        $document->setDirty('user_id', true);
        $this->assertInstanceOf(Document::class, $this->index->save($document));
    }

    /**
     * Test unique rule on existing document
     *
     * @group save
     */
    public function testIsUniqueWithNullValue(): void
    {
        $document = $this->index->get(1);
        $rules = $this->index->rulesChecker();
        $rules->add(new IsUnique([ 'user_id', 'title' ]));

        $document->title = null;
        $this->assertInstanceOf(Document::class, $this->index->save($document));
    }
}
