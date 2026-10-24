<?php
/*
 * @package      Webcode_CronScheduler
 *
 * @author       Kostadin Bashev (bashev@webcode.bg)
 * @copyright Copyright © 2020–2026 Webcode (https://webcode.bg/)
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License 3.0 (OSL-3.0)
 */

namespace Webcode\CronScheduler\Cron;

use Exception;
use Magento\Cron\Model\Schedule;

/**
 * Class Status
 * @package Webcode\CronScheduler\Cron
 */
class Status
{
    /**
     * Set cron status
     * @param Schedule|null $schedule
     * @throws Exception
     */
    public function checkstatus(?Schedule $schedule = null): void
    {
        $schedule->setMessages(__("Cron is Working"));
        $schedule->save();
    }
}
