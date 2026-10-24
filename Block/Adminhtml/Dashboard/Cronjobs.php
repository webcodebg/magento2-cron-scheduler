<?php
/*
 * @package      Webcode_CronScheduler
 *
 * @author       Kostadin Bashev (bashev@webcode.bg)
 * @copyright Copyright © 2020–2026 Webcode (https://webcode.bg/)
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License 3.0 (OSL-3.0)
 */

declare(strict_types=1);

namespace Webcode\CronScheduler\Block\Adminhtml\Dashboard;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Cron\Model\Schedule;
use Magento\Store\Model\ScopeInterface;
use Webcode\CronScheduler\Model\ResourceModel\Schedule\Collection;
use Webcode\CronScheduler\Model\ResourceModel\Schedule\CollectionFactory;

class Cronjobs extends Template
{
    /**
     * Dashboard enable/disable status
     */
    public const XML_PATH_DASHBOARD_ENABLE_STATUS = 'cronscheduler/general/cronscheduler_dashboard_enabled';

    /**
     * Display total records on dashboard
     */
    public const TOTAL_RECORDS_ON_DASHBOARD = 5;

    /**
     * Class constructor.
     *
     * @param Context $context
     * @param CollectionFactory $scheduleCollectionFactory
     */
    public function __construct(
        Context $context,
        private readonly CollectionFactory $scheduleCollectionFactory
    ) {
        parent::__construct($context);
    }

    /**
     * Get Top running jobs
     *
     * @return Collection
     */
    public function getTopRunningJobs(): Collection
    {
        $collection = $this->scheduleCollectionFactory->create();

        $collection->addFieldToFilter('status', Schedule::STATUS_SUCCESS)
            ->addExpressionFieldToSelect(
                'timediff',
                'TIME_TO_SEC(TIMEDIFF(`finished_at`, `executed_at`))',
                []
            )
            ->setOrder('TIME_TO_SEC(TIMEDIFF(`finished_at`, `executed_at`))', 'DESC')
            ->setPageSize(self::TOTAL_RECORDS_ON_DASHBOARD)
            ->load();

        return $collection;
    }

    /**
     * Check store configuration value for dashboard
     *
     * @return bool
     */
    public function isDashboardActive(): bool
    {
        return $this->_scopeConfig->isSetFlag(
            self::XML_PATH_DASHBOARD_ENABLE_STATUS,
            ScopeInterface::SCOPE_STORE
        );
    }
}
