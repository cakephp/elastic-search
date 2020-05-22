<?php
declare(strict_types=1);

/**
 * MissingDocumentException file
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

use Cake\ORM\Exception\MissingEntityException;

/**
 * Exception raised when a Document could not be found.
 */
class MissingDocumentException extends MissingEntityException
{
    /**
     * @var string
     */
    protected $_messageTemplate = 'Document class %s could not be found.';
}
