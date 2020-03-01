<?php
declare(strict_types=1);

/**
 * NotImplementedException file
 *
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link          https://cakephp.org CakePHP(tm) Project
 * @since         3.0.0
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 */
namespace Cake\ElasticSearch\Exception;

use Cake\Core\Exception\Exception;

/**
 * Exception raised when a method has not been implemented.
 */
class NotImplementedException extends Exception
{
    /**
     * @var string
     */
    protected $_messageTemplate = 'Method %s has not been implemented in this context.';
}
