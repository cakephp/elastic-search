<?php
/**
 * CakePHP(tm) : Rapid Development Framework (http://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link          http://cakephp.org CakePHP(tm) Project
 * @since         0.0.1
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */
use Cake\Collection\Collection;
use Cake\Datasource\FactoryLocator;
use Cake\ElasticSearch\Document;
use Cake\ElasticSearch\View\Form\DocumentContext;
use Cake\Event\EventManager;

$callback = ['Cake\ElasticSearch\IndexRegistry', 'get'];
FactoryLocator::add('Elastic', $callback);
FactoryLocator::add('ElasticSearch', $callback);

// Attach the document context into FormHelper.
EventManager::instance()->on('View.beforeRender', function ($event) {
    $view = $event->getSubject();
    $view->Form->addContextProvider('elastic', function ($request, $data) {
        $first = null;
        if (is_array($data['entity']) || $data['entity'] instanceof Traversable) {
            $first = (new Collection($data['entity']))->first();
        }
        if ($data['entity'] instanceof Document || $first instanceof Document) {
            return new DocumentContext($request, $data);
        }
    });
});
