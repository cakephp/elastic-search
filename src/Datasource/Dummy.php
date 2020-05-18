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
 * @since         2.1.0
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 */
namespace Cake\ElasticSearch\Datasource;

use Cake\Database\Driver;

/**
 * Represents a dummy driver used only for convertions
 * between types of ElasticSearch and PHP.
 */
class Dummy extends Driver
{
    /**
     * Establishes a connection to the database server.
     *
     * @return bool True on success, false on failure.
     */
    public function connect()
    {
        return true;
    }

    /**
     * Returns whether php is able to use this driver for connecting to not.
     *
     * @return bool True if it is valid to use this driver.
     */
    public function enabled()
    {
        return true;
    }

    /**
     * Get the SQL for releasing a save point.
     *
     * @param string|int $name The table name.
     * @return string
     */
    public function releaseSavePointSQL($name)
    {
        return '';
    }

    /**
     * Get the SQL for creating a save point.
     *
     * @param string|int $name The table name.
     * @return string
     */
    public function savePointSQL($name)
    {
        return '';
    }

    /**
     * Get the SQL for rollingback a save point.
     *
     * @param string|int $name The table name.
     * @return string
     */
    public function rollbackSavePointSQL($name)
    {
        return '';
    }

    /**
     * Get the SQL for disabling foreign keys.
     *
     * @return string
     */
    public function disableForeignKeySQL()
    {
        return '';
    }

    /**
     * Get the SQL for enabling foreign keys.
     *
     * @return string
     */
    public function enableForeignKeySQL()
    {
        return '';
    }

    /**
     * Returns whether the driver supports adding or dropping constraints
     * to already created tables.
     *
     * @return bool True if driver supports dynamic constraints.
     */
    public function supportsDynamicConstraints()
    {
        return false;
    }

    /**
     * Returns whether this driver supports save points for nested transactions.
     *
     * @return bool True if save points are supported, false otherwise.
     */
    public function supportsSavePoints()
    {
        return false;
    }

    /**
     * Returns a callable function that will be used to transform a passed Query object.
     * This function, in turn, will return an instance of a Query object that has been
     * transformed to accommodate any specificities of the SQL dialect in use.
     *
     * @param string $type The type of query to be transformed
     * (select, insert, update, delete).
     * @return callable
     */
    public function queryTranslator($type)
    {
        return function ($query) use ($type) {
            return $query;
        };
    }

    /**
     * Get the schema dialect.
     *
     * Used by Cake\Database\Schema package to reflect schema and
     * generate schema.
     *
     * If all the tables that use this Driver specify their
     * own schemas, then this may return null.
     *
     * @return \Cake\Database\Schema\BaseSchema
     */
    public function schemaDialect()
    {
        return null;
    }

    /**
     * Quotes a database identifier (a column name, table name, etc..) to
     * be used safely in queries without the risk of using reserved words.
     *
     * @param string $identifier The identifier expression to quote.
     * @return string
     */
    public function quoteIdentifier($identifier)
    {
        return $identifier;
    }
}
