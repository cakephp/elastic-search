<?php
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
 * @internal      Should be used internal only - will be replaced with a real driver in
 * the future.
 * @since         2.1.0
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 */
namespace Cake\ElasticSearch\Database\Driver;

use Cake\Database\Driver;

/**
 * Represents a dummy driver used only for convertions
 * between types of ElasticSearch and PHP.
 */
class Dummy extends Driver
{
    /**
     * @inheritDoc
     */
    public function connect()
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function enabled()
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function releaseSavePointSQL($name)
    {
        return '';
    }

    /**
     * @inheritDoc
     */
    public function savePointSQL($name)
    {
        return '';
    }

    /**
     * @inheritDoc
     */
    public function rollbackSavePointSQL($name)
    {
        return '';
    }

    /**
     * @inheritDoc
     */
    public function disableForeignKeySQL()
    {
        return '';
    }

    /**
     * @inheritDoc
     */
    public function enableForeignKeySQL()
    {
        return '';
    }

    /**
     * @inheritDoc
     */
    public function supportsDynamicConstraints()
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function supportsSavePoints()
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function queryTranslator($type)
    {
        return function ($query) use ($type) {
            return $query;
        };
    }

    /**
     * @inheritDoc
     */
    public function schemaDialect()
    {
        return null;
    }

    /**
     * @inheritDoc
     */
    public function quoteIdentifier($identifier)
    {
        return $identifier;
    }
}
