<?php
declare(strict_types=1);

namespace TestApp\Model\Document;

use Cake\ElasticSearch\Document;

class ProtectedArticle extends Document
{
    protected $_accessible = [
        'title' => true,
    ];
}
