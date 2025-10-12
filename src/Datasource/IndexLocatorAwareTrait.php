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
namespace Cake\ElasticSearch\Datasource;

use Cake\Datasource\FactoryLocator;
use Cake\Datasource\Locator\LocatorInterface;
use Cake\ElasticSearch\Index;
use UnexpectedValueException;

/**
 * Contains method for setting and accessing IndexLocator instance
 */
trait IndexLocatorAwareTrait
{
    /**
     * This object's default index alias.
     */
    protected ?string $defaultIndex = null;

    /**
     * Index locator instance
     */
    protected ?LocatorInterface $_indexLocator = null;

    /**
     * Sets the index locator.
     *
     * @param \Cake\Datasource\Locator\LocatorInterface $indexLocator LocatorInterface instance.
     * @return $this
     */
    public function setIndexLocator(LocatorInterface $indexLocator)
    {
        $this->_indexLocator = $indexLocator;

        return $this;
    }

    /**
     * Gets the index locator.
     */
    public function getIndexLocator(): LocatorInterface
    {
        if ($this->_indexLocator !== null) {
            return $this->_indexLocator;
        }

        $locator = FactoryLocator::get('Elastic');
        assert(
            $locator instanceof LocatorInterface,
            '`FactoryLocator` must return an instance of Cake\Datasource\Locator\LocatorInterface for type `Elastic`.',
        );

        return $this->_indexLocator = $locator;
    }

    /**
     * Convenience method to get an index instance.
     *
     * @param string|null $alias The alias name you want to get. Should be in CamelCase format.
     *  If `null` then the value of $defaultIndex property is used.
     * @param array<string, mixed> $options The options you want to build the index with.
     *   If an index has already been loaded the registry options will be ignored.
     * @throws \UnexpectedValueException If `$alias` argument and `$defaultIndex` property both are `null`.
     * @see \Cake\ElasticSearch\Datasource\IndexLocator::get()
     */
    public function fetchIndex(?string $alias = null, array $options = []): Index
    {
        $alias ??= $this->defaultIndex;
        if (!$alias) {
            throw new UnexpectedValueException(
                'You must provide an `$alias` or set the `$defaultIndex` property to a non empty string.',
            );
        }

        $index = $this->getIndexLocator()->get($alias, $options);
        assert($index instanceof Index);

        return $index;
    }
}
