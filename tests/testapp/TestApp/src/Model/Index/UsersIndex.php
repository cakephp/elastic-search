<?php
declare(strict_types=1);

namespace TestApp\Model\Index;

use Cake\ElasticSearch\Index;

class UsersIndex extends Index
{
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->embedOne('UserType');
    }

    public function getName(): string
    {
        return 'users';
    }
}
