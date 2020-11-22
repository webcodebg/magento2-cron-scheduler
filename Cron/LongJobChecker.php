<?php
/*
 * @package      Webcode_Magento2
 *
 * @author       Kostadin Bashev (bashev@webcode.bg)
 * @copyright    Copyright © 2020 Webcode Ltd. (https://webcode.bg/)
 * @license      See LICENSE.txt for license details.
 */

namespace KiwiCommerce\CronScheduler\Cron;

use KiwiCommerce\CronScheduler\Model\ResourceModel\Schedule\CollectionFactory;
use Magento\Backend\App\Action\Context;
use Magento\Cron\Model\Schedule;
use Magento\Framework\Stdlib\DateTime\DateTime;

/**
 * LongJobChecker checks status for long cron jobs.
 */
class LongJobChecker
{
    /**
     * @var CollectionFactory
     */
    public ?CollectionFactory $scheduleCollectionFactory = null;

    /**
     * @var string
     */
    private $timePeriod = '- 3 hour';

    /**
     * Constant for killed status.
     */
    const STATUS_KILLED = 'killed';

    /**
     * @var DateTime
     */
    public DateTime $dateTime;

    /**
     * Class constructor.
     * @param DateTime $dateTime
     * @param CollectionFactory $scheduleCollectionFactory
     */
    public function __construct(
        DateTime $dateTime,
        CollectionFactory $scheduleCollectionFactory
    ) {
        $this->dateTime = $dateTime;
        $this->scheduleCollectionFactory = $scheduleCollectionFactory;
    }

    /**
     * Execute action
     */
    public function execute(): void
    {
        $collection = $this->scheduleCollectionFactory->create();
        $time = strftime('%Y-%m-%d %H:%M:%S', $this->dateTime->gmtTimestamp($this->timePeriod));

        $jobs = $collection->addFieldToFilter('status', Schedule::STATUS_RUNNING)
            ->addFieldToFilter(
                'finished_at',
                ['null' => true]
            )
            ->addFieldToFilter(
                'executed_at',
                ['lt' => $time]
            )
            ->addFieldToSelect(['schedule_id', 'pid'])
            ->load();

        foreach ($jobs as $job) {
            $pid = $job->getPid();

            $finished_at = strftime('%Y-%m-%d %H:%M:%S', $this->dateTime->gmtTimestamp());
            if (function_exists('posix_getsid') && posix_getsid($pid) === false) {
                $job->setData('status', Schedule::STATUS_ERROR);
                $job->setData('messages', __('Execution stopped due to some error.'));
                $job->setData('finished_at', $finished_at);
            } else {
                posix_kill($pid, 9);
                $job->setData('status', self::STATUS_KILLED);
                $job->setData('messages', __('It is killed as running for longer period.'));
                $job->setData('finished_at', $finished_at);
            }
            $job->save();
        }
    }
}
