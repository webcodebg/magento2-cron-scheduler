<?php
/*
 * @package      Webcode_CronScheduler
 *
 * @author       Kostadin Bashev (bashev@webcode.bg)
 * @copyright Copyright © 2020–2026 Webcode (https://webcode.bg/)
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License 3.0 (OSL-3.0)
 */

declare(strict_types=1);

namespace Webcode\CronScheduler\Model;

/**
 * The process calls LongJobChecker makes, behind a seam so a test never signals a real pid.
 */
class ProcessControl
{
    /**
     * @param int $pid
     *
     * @return bool
     */
    public function isAlive(int $pid): bool
    {
        return !function_exists('posix_getsid') || posix_getsid($pid) !== false;
    }

    /**
     * @param int $pid
     *
     * @return void
     */
    public function kill(int $pid): void
    {
        if (function_exists('posix_kill')) {
            posix_kill($pid, 9);
        }
    }
}
