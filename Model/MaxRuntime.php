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

use Magento\Framework\App\Config\ScopeConfigInterface;

/**
 * How long a cron job may run before LongJobChecker kills it, in minutes; 0 means never (see CLAUDE.md).
 */
class MaxRuntime
{
    public const string XML_PATH_DEFAULT = 'cronscheduler/general/max_runtime';

    public const string XML_PATH_JOB_PREFIX = 'cronscheduler/max_runtime/';

    public const int FALLBACK_MINUTES = 180;

    /**
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(private readonly ScopeConfigInterface $scopeConfig)
    {
    }

    /**
     * The job's own limit, else the global one, else three hours.
     *
     * @param string $jobCode
     *
     * @return int
     */
    public function minutesFor(string $jobCode): int
    {
        return $this->read(self::jobPath($jobCode))
            ?? $this->read(self::XML_PATH_DEFAULT)
            ?? self::FALLBACK_MINUTES;
    }

    /**
     * @param string $jobCode
     *
     * @return string
     */
    public static function jobPath(string $jobCode): string
    {
        return self::XML_PATH_JOB_PREFIX . $jobCode;
    }

    /**
     * @param string $path
     *
     * @return int|null Null when unset or not a non-negative number.
     */
    private function read(string $path): ?int
    {
        $value = $this->scopeConfig->getValue($path);

        return is_numeric($value) && (int)$value >= 0 ? (int)$value : null;
    }
}
