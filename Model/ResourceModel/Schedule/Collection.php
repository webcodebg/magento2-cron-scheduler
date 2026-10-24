<?php

/*
 * @package      Webcode_CronScheduler
 *
 * @author       Kostadin Bashev (bashev@webcode.bg)
 * @copyright Copyright © 2020–2026 Webcode (https://webcode.bg/)
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License 3.0 (OSL-3.0)
 */
namespace Webcode\CronScheduler\Model\ResourceModel\Schedule;

/**
 * Class Collection
 * @package Webcode\CronScheduler\Model\ResourceModel\Schedule
 */
class Collection extends \Magento\Cron\Model\ResourceModel\Schedule\Collection
{
    /**
     * @var string
     */
    protected $_idFieldName = "schedule_id";

    /**
     * Update mail status for given filter.
     * @param $data
     * @param $filter
     */
    public function updateMailStatusByJobCode($data, $filter)
    {
        $connection = $this->getConnection();

        $connection->update(
            $this->getMainTable(),
            $data,
            [
                'schedule_id <= ? ' => (int)$filter['schedule_id'],
                'job_code = ?' => $filter['job_code'],
                'status = ?' => $filter['status'],
                'error_message IS NOT NULL',
                'is_mail_sent IS NULL'
            ]
        );
    }

    /**
     * Get the last Cron Status
     * @return string | null
     */
    public function getLastCronStatus(): ?string
    {
        $this->getSelect()->reset('columns')
            ->columns(['executed_at'])
            ->where('executed_at is not null and job_code ="webcode_cronscheduler_status"')
            ->order('finished_at desc');

        $last = $this->getFirstItem();
        if ($last) {
            return $last->getExecutedAt();
        } else {
            return null;
        }
    }

    /**
     * Get Schedule task status
     * @return $this
     */
    public function getScheduleTaskStatuses(): static
    {
        $this->getSelect()->reset('columns')
            ->columns('DISTINCT(status) as status')
            ->order('status ASC');

        return $this;
    }
}
