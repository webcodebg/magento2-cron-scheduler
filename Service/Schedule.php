<?php
/*
 * @package      Webcode_CronScheduler
 *
 * @author       Kostadin Bashev (bashev@webcode.bg)
 * @copyright Copyright © 2020–2026 Webcode (https://webcode.bg/)
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License 3.0 (OSL-3.0)
 */

declare(strict_types=1);

namespace Webcode\CronScheduler\Service;

use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Webcode\CronScheduler\Model\ResourceModel\Schedule\CollectionFactory;

/**
 * Schedule instrumentation and status helpers used during cron execution and
 * on the admin listing pages.
 */
class Schedule
{
    public function __construct(
        private readonly CollectionFactory $scheduleCollectionFactory,
        private readonly ManagerInterface $messageManager,
        private readonly DateTime $datetime
    ) {
    }

    /**
     * Store the current process id on the schedule.
     *
     * @param \Magento\Cron\Model\Schedule $schedule
     *
     * @return void
     */
    public function setPid($schedule): void
    {
        if (function_exists('getmypid')) {
            $schedule->setPid(getmypid());
        }
    }

    /**
     * Calculate actual CPU/system usage (ms) and store it on the schedule.
     *
     * @param array $ru
     * @param array $rus
     * @param \Magento\Cron\Model\Schedule $schedule
     *
     * @return void
     */
    public function setCpuUsage($ru, $rus, $schedule): void
    {
        $schedule->setCpuUsage($this->rutime($ru, $rus, 'utime'));
        $schedule->setSystemUsage($this->rutime($ru, $rus, 'stime'));
    }

    /**
     * @param array $ru
     * @param array $rus
     * @param string $index
     *
     * @return float|int
     */
    private function rutime($ru, $rus, $index): float|int
    {
        return ($ru["ru_$index.tv_sec"] * 1000 + (int)$ru["ru_$index.tv_usec"] / 1000)
               - ($rus["ru_$index.tv_sec"] * 1000 + (int)$rus["ru_$index.tv_usec"] / 1000);
    }

    /**
     * Save peak memory usage (MB) on the schedule.
     *
     * @param \Magento\Cron\Model\Schedule $schedule
     *
     * @return void
     */
    public function setMemoryUsage($schedule): void
    {
        $schedule->setMemoryUsage(memory_get_peak_usage(false) / 1024 / 1024);
    }

    /**
     * Convert a time input into a stored (UTC) "Y-m-d H:i:s" datetime string.
     *
     * Accepts an HTML datetime-local value ("YYYY-MM-DDTHH:mm") or "now" (default).
     *
     * @param string $time
     *
     * @return string
     */
    public function filterTimeInput(string $time = 'now'): string
    {
        if ($time === '' || $time === 'now') {
            return gmdate('Y-m-d H:i:s', $this->datetime->gmtTimestamp());
        }

        if (preg_match('/(\d{4}-\d{2}-\d{2})T(\d{2}:\d{2})/', $time, $matches)) {
            $time = $matches[1] . ' ' . $matches[2];
        }

        return $this->datetime->gmtDate('Y-m-d H:i:s', $time);
    }

    /**
     * Add an admin message describing how long ago the last cron ran.
     *
     * @return void
     */
    public function getLastCronStatusMessage(): void
    {
        $currentTime = (int)$this->datetime->date('U');
        $lastCronStatus = strtotime((string)$this->scheduleCollectionFactory->create()->getLastCronStatus());

        if ($lastCronStatus != null) {
            $diff = floor(($currentTime - $lastCronStatus) / 60);
            $measureUnit = $diff > 1 ? 's' : '';
            if ($diff > 5) {
                if ($diff >= 60) {
                    $diff = floor($diff / 60);
                    $measureUnit = $diff > 1 ? 's' : '';
                    $this->messageManager->addErrorMessage(
                        __('Last cron execution is older than %1 hour%2', $diff, $measureUnit)
                    );
                } else {
                    $this->messageManager->addErrorMessage(
                        __('Last cron execution is older than %1 minute%2', $diff, $measureUnit)
                    );
                }
            } else {
                $this->messageManager->addSuccessMessage(
                    __('Last cron execution was %1 minute%2 ago', $diff, $measureUnit)
                );
            }
        } else {
            $this->messageManager->addErrorMessage(__('No cron execution found'));
        }
    }
}
