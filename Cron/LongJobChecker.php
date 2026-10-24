<?php
/*
 * @package      Webcode_CronScheduler
 *
 * @author       Kostadin Bashev (bashev@webcode.bg)
 * @copyright Copyright © 2020–2026 Webcode (https://webcode.bg/)
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License 3.0 (OSL-3.0)
 */

declare(strict_types=1);

namespace Webcode\CronScheduler\Cron;

use Magento\Cron\Model\Schedule;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Webcode\CronScheduler\Model\MaxRuntime;
use Webcode\CronScheduler\Model\ProcessControl;
use Webcode\CronScheduler\Model\ResourceModel\Schedule\CollectionFactory;

/**
 * LongJobChecker kills cron jobs that run past their own limit (see CLAUDE.md).
 */
class LongJobChecker
{
    /**
     * @var CollectionFactory
     */
    public ?CollectionFactory $scheduleCollectionFactory = null;

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
     *
     * @param DateTime $dateTime
     * @param CollectionFactory $scheduleCollectionFactory
     * @param MaxRuntime $maxRuntime
     * @param ProcessControl $processControl
     */
    public function __construct(
        DateTime $dateTime,
        CollectionFactory $scheduleCollectionFactory,
        private readonly MaxRuntime $maxRuntime,
        private readonly ProcessControl $processControl
    ) {
        $this->dateTime = $dateTime;
        $this->scheduleCollectionFactory = $scheduleCollectionFactory;
    }

    /**
     * Execute action
     */
    public function execute(): void
    {
        $jobs = $this->scheduleCollectionFactory->create()
            ->addFieldToFilter('status', Schedule::STATUS_RUNNING)
            ->addFieldToFilter('finished_at', ['null' => true])
            ->addFieldToFilter('executed_at', ['notnull' => true])
            ->addFieldToSelect(['schedule_id', 'job_code', 'pid', 'executed_at']);

        $now = $this->dateTime->gmtTimestamp();

        foreach ($jobs as $job) {
            $minutes = $this->maxRuntime->minutesFor((string)$job->getData('job_code'));
            $startedAt = strtotime($job->getData('executed_at') . ' UTC');
            $pid = (int)$job->getData('pid');

            // No pid: posix_kill(0) would signal this checker's own process group.
            if ($minutes === 0 || $startedAt === false || $now - $startedAt < $minutes * 60 || $pid <= 0) {
                continue;
            }

            if (!$this->processControl->isAlive($pid)) {
                $job->setData('status', Schedule::STATUS_ERROR);
                $job->setData('messages', __('Execution stopped due to some error.'));
            } else {
                $this->processControl->kill($pid);
                $job->setData('status', self::STATUS_KILLED);
                $job->setData('messages', __('It is killed as running for longer period.'));
            }
            $job->setData('finished_at', $this->dateTime->gmtDate('Y-m-d H:i:s'));
            $job->save();
        }
    }
}
