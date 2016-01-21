<?php
/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\CatalogSearch\Model\ResourceModel\Fulltext;

use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\DB\Select;
use Magento\Framework\Exception\StateException;
use Magento\Framework\Search\Adapter\Mysql\TemporaryStorage;

/**
 * Fulltext Collection
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Collection extends \Magento\Catalog\Model\ResourceModel\Product\Collection
{
    /**
     * Catalog search data
     *
     * @var \Magento\Search\Model\QueryFactory
     */
    protected $queryFactory = null;

    /** @var string */
    private $queryText;

    /** @var string|null */
    private $order = null;

    /** @var string */
    private $searchRequestName;

    /**
     * @var \Magento\Framework\Search\Adapter\Mysql\TemporaryStorageFactory
     */
    private $temporaryStorageFactory;

    /**
     * @var \Magento\Framework\Api\Search\SearchInterface
     */
    private $search;

    /**
     * @var \Magento\Framework\Api\Search\SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @var \Magento\Framework\Api\Search\SearchResultInterface
     */
    private $searchResult;

    /**
     * @var FilterBuilder
     */
    private $filterBuilder;

    /**
     * @param \Magento\Framework\Data\Collection\EntityFactory $entityFactory
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Magento\Framework\Data\Collection\Db\FetchStrategyInterface $fetchStrategy
     * @param \Magento\Framework\Event\ManagerInterface $eventManager
     * @param \Magento\Eav\Model\Config $eavConfig
     * @param \Magento\Framework\App\ResourceConnection $resource
     * @param \Magento\Eav\Model\EntityFactory $eavEntityFactory
     * @param \Magento\Catalog\Model\ResourceModel\Helper $resourceHelper
     * @param \Magento\Framework\Validator\UniversalFactory $universalFactory
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Framework\Module\Manager $moduleManager
     * @param \Magento\Catalog\Model\Indexer\Product\Flat\State $catalogProductFlatState
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Catalog\Model\Product\OptionFactory $productOptionFactory
     * @param \Magento\Catalog\Model\ResourceModel\Url $catalogUrl
     * @param \Magento\Framework\Stdlib\DateTime\TimezoneInterface $localeDate
     * @param \Magento\Customer\Model\Session $customerSession
     * @param \Magento\Framework\Stdlib\DateTime $dateTime
     * @param \Magento\Customer\Api\GroupManagementInterface $groupManagement
     * @param \Magento\Search\Model\QueryFactory $catalogSearchData
     * @param \Magento\Framework\Search\Adapter\Mysql\TemporaryStorageFactory $temporaryStorageFactory
     * @param \Magento\Framework\Api\Search\SearchInterface $search
     * @param \Magento\Framework\Api\Search\SearchCriteriaBuilder $searchCriteriaBuilder
     * @param \Magento\Framework\DB\Adapter\AdapterInterface|null $connection
     * @param string $searchRequestName
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        \Magento\Framework\Data\Collection\EntityFactory $entityFactory,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Framework\Data\Collection\Db\FetchStrategyInterface $fetchStrategy,
        \Magento\Framework\Event\ManagerInterface $eventManager,
        \Magento\Eav\Model\Config $eavConfig,
        \Magento\Framework\App\ResourceConnection $resource,
        \Magento\Eav\Model\EntityFactory $eavEntityFactory,
        \Magento\Catalog\Model\ResourceModel\Helper $resourceHelper,
        \Magento\Framework\Validator\UniversalFactory $universalFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\Module\Manager $moduleManager,
        \Magento\Catalog\Model\Indexer\Product\Flat\State $catalogProductFlatState,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Catalog\Model\Product\OptionFactory $productOptionFactory,
        \Magento\Catalog\Model\ResourceModel\Url $catalogUrl,
        \Magento\Framework\Stdlib\DateTime\TimezoneInterface $localeDate,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Framework\Stdlib\DateTime $dateTime,
        \Magento\Customer\Api\GroupManagementInterface $groupManagement,
        \Magento\Search\Model\QueryFactory $catalogSearchData,
        \Magento\Framework\Search\Adapter\Mysql\TemporaryStorageFactory $temporaryStorageFactory,
        \Magento\Framework\Api\Search\SearchInterface $search,
        \Magento\Framework\Api\Search\SearchCriteriaBuilder $searchCriteriaBuilder,
        FilterBuilder $filterBuilder,
        \Magento\Framework\DB\Adapter\AdapterInterface $connection = null,
        $searchRequestName = 'catalog_view_container'
    ) {
        $this->queryFactory = $catalogSearchData;
        parent::__construct(
            $entityFactory,
            $logger,
            $fetchStrategy,
            $eventManager,
            $eavConfig,
            $resource,
            $eavEntityFactory,
            $resourceHelper,
            $universalFactory,
            $storeManager,
            $moduleManager,
            $catalogProductFlatState,
            $scopeConfig,
            $productOptionFactory,
            $catalogUrl,
            $localeDate,
            $customerSession,
            $dateTime,
            $groupManagement,
            $connection
        );
        $this->temporaryStorageFactory = $temporaryStorageFactory;
        $this->searchRequestName = $searchRequestName;
        $this->search = $search;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->filterBuilder = $filterBuilder;
    }

    /**
     * Apply attribute filter to facet collection
     *
     * @param string $field
     * @param null $condition
     * @return $this
     */
    public function addFieldToFilter($field, $condition = null)
    {
        if ($this->searchResult !== null) {
            throw new \RuntimeException('Illegal state');
        }

        if (!is_array($condition) || !in_array(key($condition), ['from', 'to'])) {
            $this->filterBuilder->setField($field);
            $this->filterBuilder->setValue($condition);
            $this->searchCriteriaBuilder->addFilter($this->filterBuilder->create());
        } else {
            if (!empty($condition['from'])) {
                $this->filterBuilder->setField("{$field}.from");
                $this->filterBuilder->setValue($condition['from']);
                $this->searchCriteriaBuilder->addFilter($this->filterBuilder->create());
            }
            if (!empty($condition['to'])) {
                $this->filterBuilder->setField("{$field}.to");
                $this->filterBuilder->setValue($condition['to']);
                $this->searchCriteriaBuilder->addFilter($this->filterBuilder->create());
            }
        }
        return $this;
    }

    /**
     * Add search query filter
     *
     * @param string $query
     * @return $this
     */
    public function addSearchFilter($query)
    {
        $this->queryText = trim($this->queryText . ' ' . $query);
        return $this;
    }

    /**
     * @inheritdoc
     */
    protected function _renderFiltersBefore()
    {
        if ($this->queryText) {
            $this->filterBuilder->setField('search_term');
            $this->filterBuilder->setValue($this->queryText);
            $this->searchCriteriaBuilder->addFilter($this->filterBuilder->create());
        }

        $priceRangeCalculation = $this->_scopeConfig->getValue(
            \Magento\Catalog\Model\Layer\Filter\Dynamic\AlgorithmFactory::XML_PATH_RANGE_CALCULATION,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
        if ($priceRangeCalculation) {
            $this->filterBuilder->setField('price_dynamic_algorithm');
            $this->filterBuilder->setValue($priceRangeCalculation);
            $this->searchCriteriaBuilder->addFilter($this->filterBuilder->create());
        }

        $searchCriteria = $this->searchCriteriaBuilder->create();
        $searchCriteria->setRequestName($this->searchRequestName);
        $this->searchResult = $this->search->search($searchCriteria);

        $temporaryStorage = $this->temporaryStorageFactory->create();
        $table = $temporaryStorage->storeApiDocuments($this->searchResult->getItems());

        $this->getSelect()->joinInner(
            [
                'search_result' => $table->getName(),
            ],
            'e.entity_id = search_result.' . TemporaryStorage::FIELD_ENTITY_ID,
            []
        );

        $this->_totalRecords = $this->searchResult->getTotalCount();

        if ($this->order && 'relevance' === $this->order['field']) {
            $this->getSelect()->order('search_result.'. TemporaryStorage::FIELD_SCORE . ' ' . $this->order['dir']);
        }
        return parent::_renderFiltersBefore();
    }

    /**
     * @return $this
     */
    protected function _renderFilters()
    {
        $this->_filters = [];
        return parent::_renderFilters();
    }

    /**
     * Set Order field
     *
     * @param string $attribute
     * @param string $dir
     * @return $this
     */
    public function setOrder($attribute, $dir = Select::SQL_DESC)
    {
        $this->order = ['field' => $attribute, 'dir' => $dir];
        if ($attribute != 'relevance') {
            parent::setOrder($attribute, $dir);
        }
        return $this;
    }

    /**
     * Stub method for compatibility with other search engines
     *
     * @return $this
     */
    public function setGeneralDefaultQuery()
    {
        return $this;
    }

    /**
     * Return field faceted data from faceted search result
     *
     * @param string $field
     * @return array
     * @throws StateException
     */
    public function getFacetedData($field)
    {
        $this->_renderFilters();
        $result = [];
        $aggregations = $this->searchResult->getAggregations();
        $bucket = $aggregations->getBucket($field . '_bucket');
        if ($bucket) {
            foreach ($bucket->getValues() as $value) {
                $metrics = $value->getMetrics();
                $result[$metrics['value']] = $metrics;
            }
        } else {
            throw new StateException(__('Bucket do not exists'));
        }
        return $result;
    }

    /**
     * Specify category filter for product collection
     *
     * @param \Magento\Catalog\Model\Category $category
     * @return $this
     */
    public function addCategoryFilter(\Magento\Catalog\Model\Category $category)
    {
        $this->addFieldToFilter('category_ids', $category->getId());
        return parent::addCategoryFilter($category);
    }

    /**
     * Set product visibility filter for enabled products
     *
     * @param array $visibility
     * @return $this
     */
    public function setVisibility($visibility)
    {
        $this->addFieldToFilter('visibility', $visibility);
        return parent::setVisibility($visibility);
    }
}
