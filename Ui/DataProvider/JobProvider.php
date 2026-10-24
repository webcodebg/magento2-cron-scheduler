<?php
/*
 * @package      Webcode_CronScheduler
 *
 * @author       Kostadin Bashev (bashev@webcode.bg)
 * @copyright Copyright © 2020–2026 Webcode (https://webcode.bg/)
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License 3.0 (OSL-3.0)
 */

declare(strict_types=1);

namespace Webcode\CronScheduler\Ui\DataProvider;

use Magento\Framework\Api\Filter;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem\Directory\ReadFactory;
use Magento\Ui\DataProvider\AbstractDataProvider;
use Webcode\CronScheduler\Service\Cronjob;

/**
 * Class JobProvider
 *
 * @package Webcode\CronScheduler\Ui\DataProvider
 */
class JobProvider extends AbstractDataProvider
{
    /**
     * @var integer
     */
    protected $size = 20;

    /**
     * @var integer
     */
    protected $offset = 1;

    /**
     * @var array
     */
    protected $likeFilters = [];

    /**
     * @var array
     */
    protected $rangeFilters = [];

    /**
     * @var string
     */
    protected $sortField = 'code';

    /**
     * @var string
     */
    protected $sortDir = 'asc';

    /**
     * @var DirectoryList
     */
    private $directoryList = null;

    /**
     * @var ReadFactory
     */
    private $directoryRead = null;

    /**
     * Class constructor
     *
     * @param string $name
     * @param string $primaryFieldName
     * @param string $requestFieldName
     * @param ReadFactory $directoryRead
     * @param DirectoryList $directoryList
     * @param Cronjob $jobHelper
     * @param array $meta
     * @param array $data
     */
    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        ReadFactory $directoryRead,
        DirectoryList $directoryList,
        private readonly Cronjob $jobHelper,
        array $meta = [],
        array $data = []
    ) {
        $this->directoryRead = $directoryRead;
        $this->directoryList = $directoryList;
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
    }

    /**
     * Set the limit of the collection
     *
     * @param int $offset
     * @param int $size
     */
    public function setLimit(
        $offset,
        $size
    ) {
        $this->size = $size;
        $this->offset = $offset;
    }

    /**
     * Get the collection
     *
     * @return array
     */
    public function getData(): array
    {
        $data = array_values($this->jobHelper->getJobData());

        $totalRecords = count($data);

        $sortField = $this->sortField;
        $sortDir = $this->sortDir;
        usort($data, static function($a, $b) use ($sortField, $sortDir)
        {
            if ($sortDir === "asc") {
                return $a[$sortField] > $b[$sortField] ? 1 : -1;
            }

            return $a[$sortField] < $b[$sortField] ? 1 : -1;
        });

        #filters
        foreach ($this->likeFilters as $column => $value) {
            $data = array_filter($data, function($item) use ($column, $value)
            {
                return stripos($item[$column], $value) !== false;
            });
        }

        #pagination
        $data = array_slice($data, ($this->offset - 1) * $this->size, $this->size);

        return [
            'totalRecords' => $totalRecords,
            'items' => $data,
        ];
    }

    /**
     * Add filters to the collection
     *
     * @param Filter $filter
     */
    public function addFilter(Filter $filter)
    {
        if ($filter->getConditionType() === 'like') {
            $this->likeFilters[$filter->getField()] = substr($filter->getValue(), 1, -1);
        } elseif ($filter->getConditionType() === 'eq') {
            $this->likeFilters[$filter->getField()] = $filter->getValue();
        } elseif ($filter->getConditionType() === 'gteq') {
            $this->rangeFilters[$filter->getField()]['from'] = $filter->getValue();
        } elseif ($filter->getConditionType() === 'lteq') {
            $this->rangeFilters[$filter->getField()]['to'] = $filter->getValue();
        }
    }

    /**
     * Set the order of the collection
     *
     * @param string $field
     * @param string $direction
     */
    public function addOrder(
        $field,
        $direction
    ) {
        $this->sortField = $field;
        $this->sortDir = strtolower($direction);
    }
}
