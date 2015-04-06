<?php
namespace Cake\ElasticSearch\Test;

use Cake\ElasticSearch\Marshaller;
use Cake\ElasticSearch\Document;
use Cake\ElasticSearch\Type;

/**
 * Test entity for mass assignment.
 */
class ProtectedArticle extends Document
{

    protected $_accessible = [
        'title' => true,
    ];
}


/**
 * Test case for the marshaller.
 */
class MarshallerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Setup method.
     *
     * @return void
     */
    public function setUp()
    {
        parent::setUp();
        $connection = $this->getMock('Cake\ElasticSearch\Datasource\Connection', [], [], '', false);
        $this->type = new Type([
            'connection' => $connection,
            'name' => 'articles'
        ]);
    }

    /**
     * test marshalling a simple object.
     *
     * @return void
     */
    public function testOneSimple()
    {
        $data = [
            'title' => 'Testing',
            'body' => 'Elastic text',
            'user_id' => 1,
        ];
        $marshaller = new Marshaller($this->type);
        $result = $marshaller->one($data);

        $this->assertInstanceOf('Cake\ElasticSearch\Document', $result);
        $this->assertSame($data['title'], $result->title);
        $this->assertSame($data['body'], $result->body);
        $this->assertSame($data['user_id'], $result->user_id);
    }

    /**
     * test marshalling with fieldList
     *
     * @return void
     */
    public function testOneFieldList()
    {
        $data = [
            'title' => 'Testing',
            'body' => 'Elastic text',
            'user_id' => 1,
        ];
        $marshaller = new Marshaller($this->type);
        $result = $marshaller->one($data, ['fieldList' => ['title']]);

        $this->assertSame($data['title'], $result->title);
        $this->assertNull($result->body);
        $this->assertNull($result->user_id);
    }

    /**
     * test marshalling with accessibleFields
     *
     * @return void
     */
    public function testOneAccsesibleFields()
    {
        $data = [
            'title' => 'Testing',
            'body' => 'Elastic text',
            'user_id' => 1,
        ];
        $this->type->entityClass(__NAMESPACE__ . '\ProtectedArticle');

        $marshaller = new Marshaller($this->type);
        $result = $marshaller->one($data);

        $this->assertSame($data['title'], $result->title);
        $this->assertNull($result->body);
        $this->assertNull($result->user_id);

        $result = $marshaller->one($data, ['accessibleFields' => ['body' => true]]);

        $this->assertSame($data['title'], $result->title);
        $this->assertSame($data['body'], $result->body);
        $this->assertNull($result->user_id);
    }
}
