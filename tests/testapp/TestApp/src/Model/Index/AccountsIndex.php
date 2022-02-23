<?php
declare(strict_types=1);

namespace TestApp\Model\Index;

use Cake\ElasticSearch\Index;

class AccountsIndex extends Index
{
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->embedMany('User', ['property' => 'users']);
    }

    public function getName(): string
    {
        return 'accounts';
    }
}
