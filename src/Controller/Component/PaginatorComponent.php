<?php

namespace Cake\ElasticSearch\Controller\Component;

use Cake\Controller\Component;
use Cake\Datasource\RepositoryInterface;
use Cake\Network\Exception\NotFoundException;
use Cake\ElasticSearch\Query;

class PaginatorComponent extends Component
{

    /**
     * Default pagination settings.
     *
     * When calling paginate() these settings will be merged with the configuration
     * you provide.
     *
     * - `maxLimit` - The maximum limit users can choose to view. Defaults to 100
     * - `limit` - The initial number of items per page. Defaults to 20.
     * - `page` - The starting page, defaults to 1.
     * - `whitelist` - A list of parameters users are allowed to set using request
     *   parameters. Modifying this list will allow users to have more influence
     *   over pagination, be careful with what you permit.
     *
     * @var array
     */
    protected $_defaultConfig = [
        'page' => 1,
        'limit' => 20,
        'maxLimit' => 100,
        'whitelist' => ['limit', 'sort', 'page', 'direction']
    ];

    /**
     * Events supported by this component.
     *
     * @return array
     */
    public function implementedEvents()
    {
        return [];
    }

    /**
     * Handles automatic pagination of type records.
     *
     * ### Configuring pagination
     *
     * When calling `paginate()` you can use the $settings parameter to pass in pagination settings.
     * These settings are used to build the queries made and control other pagination settings.
     *
     * If your settings contain a key with the current type's alias. The data inside that key will be used.
     * Otherwise the top level configuration will be used.
     *
     * ```
     *  $settings = [
     *    'limit' => 20,
     *    'maxLimit' => 100
     *  ];
     *  $results = $paginator->paginate($type, $settings);
     * ```
     *
     * The above settings will be used to paginate any Type. You can configure Type specific settings by
     * keying the settings with the Type alias.
     *
     * ```
     *  $settings = [
     *    'Articles' => [
     *      'limit' => 20,
     *      'maxLimit' => 100
     *    ],
     *    'Comments' => [ ... ]
     *  ];
     *  $results = $paginator->paginate($type, $settings);
     * ```
     *
     * This would allow you to have different pagination settings for `Articles` and `Comments` types.
     *
     * ### Controlling sort fields
     *
     * By default CakePHP will automatically allow sorting on any column on the Type object being
     * paginated. Often times you will want to allow sorting on either associated columns or calculated
     * fields. In these cases you will need to define a whitelist of all the columns you wish to allow
     * sorting on. You can define the whitelist in the `$settings` parameter:
     *
     * ```
     * $settings = [
     *   'Articles' => [
     *     'finder' => 'custom',
     *     'sortWhitelist' => ['title', 'author_id', 'comment_count'],
     *   ]
     * ];
     * ```
     *
     * Passing an empty array as whitelist disallows sorting altogether.
     *
     * ### Paginating with custom finders
     *
     * You can paginate with any find type defined on your Type using the `finder` option.
     *
     * ```
     *  $settings = [
     *    'Articles' => [
     *      'finder' => 'popular'
     *    ]
     *  ];
     *  $results = $paginator->paginate($type, $settings);
     * ```
     *
     * Would paginate using the `find('popular')` method.
     *
     * You can also pass an already created instance of a query to this method:
     *
     * ```
     * $query = $this->Articles->find('popular')->matching('Tags', function ($q) {
     *   return $q->where(['name' => 'CakePHP'])
     * });
     * $results = $paginator->paginate($query);
     * ```
     *
     * @param \Cake\Datasource\RepositoryInterface|\Cake\ElasticSearch\Query $object The Type or query to paginate.
     * @param array $settings The settings/configuration used for pagination.
     * @return array Query results
     * @throws \Cake\Network\Exception\NotFoundException
     */
    public function paginate($object, array $settings = [])
    {
        if ($object instanceof Query) {
            $query = $object;
            $object = $query->repository();
        }

        $alias = $object->alias();
        $options = $this->mergeOptions($alias, $settings);
        $options = $this->validateSort($object, $options);
        $options = $this->checkLimit($options);

        $options += ['page' => 1];
        $options['page'] = (int)$options['page'] < 1 ? 1 : (int)$options['page'];
        list($finder, $options) = $this->_extractFinder($options);

        if (empty($query)) {
            $query = $object->find($finder, $options);
        } else {
            $query->applyOptions($options);
        }

        $results = $query->all();

        $numResults = $results->count();
        $count = $numResults ? $results->getTotalHits() : 0;

        $defaults = $this->getDefaults($alias, $settings);
        unset($defaults[0]);

        $page = $options['page'];
        $limit = $options['limit'];
        $pageCount = (int)ceil($count / $limit);
        $requestedPage = $page;
        $page = max(min($page, $pageCount), 1);
        $request = $this->_registry->getController()->request;

        $order = (array)$options['order'];
        $sortDefault = $directionDefault = false;
        if (!empty($defaults['order']) && count($defaults['order']) == 1) {
            $sortDefault = key($defaults['order']);
            $directionDefault = current($defaults['order']);
        }

        $paging = [
            'finder' => $finder,
            'page' => $page,
            'current' => $numResults,
            'count' => $count,
            'perPage' => $limit,
            'prevPage' => ($page > 1),
            'nextPage' => ($count > ($page * $limit)),
            'pageCount' => $pageCount,
            'sort' => key($order),
            'direction' => current($order),
            'limit' => $defaults['limit'] != $limit ? $limit : null,
            'sortDefault' => $sortDefault,
            'directionDefault' => $directionDefault
        ];

        if (!isset($request['paging'])) {
            $request['paging'] = [];
        }
        $request['paging'] = [$alias => $paging] + (array)$request['paging'];

        if ($requestedPage > $page) {
            throw new NotFoundException();
        }

        return $results->getResults();
    }

    /**
     * Extracts the finder name and options out of the provided pagination options
     *
     * @param array $options the pagination options
     * @return array An array containing in the first position the finder name and
     * in the second the options to be passed to it
     */
    protected function _extractFinder($options)
    {
        $type = !empty($options['finder']) ? $options['finder'] : 'all';
        unset($options['finder'], $options['maxLimit']);

        if (is_array($type)) {
            $options = (array)current($type) + $options;
            $type = key($type);
        }

        return [$type, $options];
    }

    /**
     * Merges the various options that Pagination uses.
     * Pulls settings together from the following places:
     *
     * - General pagination settings
     * - Type specific settings.
     * - Request parameters
     *
     * The result of this method is the aggregate of all the option sets combined together. You can change
     * config value `whitelist` to modify which options/values can be set using request parameters.
     *
     * @param string $alias Type alias being paginated, if the general settings has a key with this value
     *   that key's settings will be used for pagination instead of the general ones.
     * @param array $settings The settings to merge with the request data.
     * @return array Array of merged options.
     */
    public function mergeOptions($alias, $settings)
    {
        $defaults = $this->getDefaults($alias, $settings);
        $request = $this->_registry->getController()->request;
        $request = array_intersect_key($request->query, array_flip($this->_config['whitelist']));
        return array_merge($defaults, $request);
    }

    /**
     * Get the default settings for a Type. If there are no settings for a specific Type, the general settings
     * will be used.
     *
     * @param string $alias Type name to get default settings for.
     * @param array $defaults The defaults to use for combining settings.
     * @return array An array of pagination defaults for a Type, or the general settings.
     */
    public function getDefaults($alias, $defaults)
    {
        if (isset($defaults[$alias])) {
            $defaults = $defaults[$alias];
        }
        if (isset($defaults['limit']) &&
            (empty($defaults['maxLimit']) || $defaults['limit'] > $defaults['maxLimit'])
        ) {
            $defaults['maxLimit'] = $defaults['limit'];
        }
        return $defaults + $this->config();
    }

    /**
     * Validate that the desired sorting can be performed on the $object. Only fields or
     * virtualFields can be sorted on. The direction param will also be sanitized. Lastly
     * sort + direction keys will be converted into the Type friendly order key.
     *
     * You can use the whitelist parameter to control which columns/fields are available for sorting.
     * This helps prevent users from ordering large result sets on un-indexed values.
     *
     * If you need to sort on associated columns or synthetic properties you will need to use a whitelist.
     *
     * Any columns listed in the sort whitelist will be implicitly trusted. You can use this to sort
     * on synthetic columns, or columns added in custom find operations that may not exist in the schema.
     *
     * @param \Cake\Datasource\RepositoryInterface $object Repository object.
     * @param array $options The pagination options being used for this request.
     * @return array An array of options with sort + direction removed and replaced with order if possible.
     */
    public function validateSort(RepositoryInterface $object, array $options)
    {
        if (isset($options['sort'])) {
            $direction = null;
            if (isset($options['direction'])) {
                $direction = strtolower($options['direction']);
            }
            if (!in_array($direction, ['asc', 'desc'])) {
                $direction = 'asc';
            }
            $options['order'] = [$options['sort'] => $direction];
        }
        unset($options['sort'], $options['direction']);

        if (empty($options['order'])) {
            $options['order'] = [];
        }
        if (!is_array($options['order'])) {
            return $options;
        }

        $inWhitelist = false;
        if (isset($options['sortWhitelist'])) {
            $field = key($options['order']);
            $inWhitelist = in_array($field, $options['sortWhitelist'], true);
            if (!$inWhitelist) {
                $options['order'] = [];
                return $options;
            }
        }

        $options['order'] = $this->_prefix($object, $options['order'], $inWhitelist);
        return $options;
    }

    /**
     * Prefixes the field with the type alias if possible.
     *
     * @param \Cake\Datasource\RepositoryInterface $object Repository object.
     * @param array $order Order array.
     * @param bool $whitelisted Whether or not the field was whitelisted
     * @return array Final order array.
     */
    protected function _prefix(RepositoryInterface $object, $order, $whitelisted = false)
    {
        $typeAlias = $object->alias();
        $typeOrder = [];
        foreach ($order as $key => $value) {
            if (is_numeric($key)) {
                $typeOrder[] = $value;
                continue;
            }
            $field = $key;
            $alias = $typeAlias;

            if (strpos($key, '.') !== false) {
                list($alias, $field) = explode('.', $key);
            }
            $correctAlias = ($typeAlias === $alias);

            if ($correctAlias && $whitelisted) {
                // Disambiguate fields in schema. As id is quite common.
                if ($object->hasField($field)) {
                    $field = $alias . '.' . $field;
                }
                $typeOrder[$field] = $value;
            } elseif ($correctAlias && $object->hasField($field)) {
                $typeOrder[$typeAlias . '.' . $field] = $value;
            } elseif (!$correctAlias && $whitelisted) {
                $typeOrder[$alias . '.' . $field] = $value;
            }
        }
        return $typeOrder;
    }

    /**
     * Check the limit parameter and ensure it's within the maxLimit bounds.
     *
     * @param array $options An array of options with a limit key to be checked.
     * @return array An array of options for pagination
     */
    public function checkLimit(array $options)
    {
        $options['limit'] = (int)$options['limit'];
        if (empty($options['limit']) || $options['limit'] < 1) {
            $options['limit'] = 1;
        }
        $options['limit'] = min($options['limit'], $options['maxLimit']);
        return $options;
    }
}
