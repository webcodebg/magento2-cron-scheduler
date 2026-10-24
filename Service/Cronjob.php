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

use Magento\Cron\Model\Config\Reader\Db;
use Magento\Cron\Model\Config\Reader\Xml;
use Magento\Cron\Model\ConfigInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Reads and queries the cron job configuration (xml + db) for the admin UI.
 */
class Cronjob
{
    /**
     * Suffix template appended when cloning a job for multiple expressions.
     */
    private const APPEND_TEMPLATE = '_cron_{$counter}';

    /**
     * Cron job source classifications.
     */
    public const CRON_DB_XML = 'db_xml';
    public const CRON_DB = 'db';
    public const CRON_XML = 'xml';
    public const CRON_OTHER = 'other';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly Db $dbReader,
        private readonly Xml $reader,
        private readonly ConfigInterface $cronConfig
    ) {
    }

    /**
     * Get list of cron jobs.
     *
     * @return array
     */
    public function getJobData(): array
    {
        $data = [];
        $configJobs = $this->cronConfig->getJobs();

        foreach ($configJobs as $group => $jobs) {
            foreach ($jobs as $code => $job) {
                $job = $this->setJobData($job);
                $job['code'] = $code;
                $job['group'] = $group;
                $job['jobtype'] = $this->getJobcodeType($code, $group);
                $data[$code] = $job;
            }
        }

        return $data;
    }

    /**
     * Get cron job detail.
     *
     * @param string $jobcode
     *
     * @return array
     */
    public function getJobDetail($jobcode): array
    {
        $data = [];
        $configJobs = $this->cronConfig->getJobs();

        foreach ($configJobs as $group => $jobs) {
            foreach ($jobs as $code => $job) {
                if ($code == $jobcode) {
                    $job = $this->setJobData($job);
                    $job['code'] = $code;
                    $job['group'] = $group;
                    $data = $job;
                    break;
                }
            }
        }

        return $data;
    }

    /**
     * Set job data for given job
     *
     * @param array $job
     *
     * @return array
     */
    private function setJobData($job): array
    {
        if (!isset($job['config_schedule'])) {
            if (isset($job['schedule'])) {
                $job['config_schedule'] = $job['schedule'];
            } else {
                if (isset($job['config_path'])) {
                    $job['config_schedule'] = $this->scopeConfig->getValue(
                        $job['config_path'],
                        ScopeInterface::SCOPE_STORE
                    );
                } else {
                    $job['config_schedule'] = "";
                }
            }
        }
        if (!isset($job['is_active'])) {
            $job['is_active'] = 1;
        }

        return $job;
    }

    /**
     * Check is job code active
     *
     * @param string $jobCode
     *
     * @return bool
     */
    public function isJobActive($jobCode): bool
    {
        $jobDetail = $this->getJobDetail($jobCode);

        return isset($jobDetail['is_active']) && $jobDetail['is_active'] == 1;
    }

    /**
     * Filter Job codes as applied filters
     *
     * @param array $filters
     *
     * @return array
     */
    public function getAllFilterJobCodes($filters): array
    {
        $data = array_values($this->getJobData());
        $result = [];
        foreach ($filters as $column => $value) {
            $data = array_filter($data, function ($item) use ($column, $value) {
                return stripos($item[$column], $value) !== false;
            });
        }

        if (!empty($data)) {
            $result = array_column($data, 'code');
        }

        return $result;
    }

    /**
     * Create unique job code name for multiple expression
     *
     * @param array $jobData
     * @param string $jobCode
     * @param int $counter
     *
     * @return array
     */
    public function getCronJobName($jobData, $jobCode, $counter): array
    {
        $data = array_values($jobData);
        $result = [];
        $existingJobCode = array_column($data, 'code');

        for ($i = $counter; $i <= $counter + 100; $i++) {
            $cronExprString = strtr(self::APPEND_TEMPLATE, ['{$counter}' => $i]);
            $jobCodeCheck = $jobCode . $cronExprString;

            if (!in_array($jobCodeCheck, $existingJobCode)) {
                $result['jobcode'] = $jobCodeCheck;
                $result['counter'] = $i + 1;
                $result['status'] = "success";
                break;
            }
        }

        return $result;
    }

    /**
     * Check cron exists with same instance method and expression
     *
     * @param array $jobData
     * @param string $cronExpr
     * @param array $data
     *
     * @return bool
     */
    public function checkIfCronExists($jobData, $cronExpr, $data): bool
    {
        $result = false;
        foreach ($jobData as $job) {
            if (isset($job['instance']) && $job['instance'] == $data['instance'] && $job['method'] == $data['method']) {
                if (isset($job['schedule']) && $job['schedule'] == $cronExpr) {
                    $result = true;
                    break;
                }
                if (isset($job['config_schedule']) && $job['config_schedule'] == $cronExpr) {
                    $result = true;
                    break;
                }
            }
        }

        return $result;
    }

    /**
     * Trim the given array
     *
     * @param array $array
     *
     * @return array
     */
    public function trimArray($array): array
    {
        return array_map('trim', $array);
    }

    /**
     * Check is jobcode of xml
     *
     * @param string $jobCode
     * @param string $group
     *
     * @return bool
     */
    public function isXMLJobcode($jobCode, $group): bool
    {
        $configJobs = $this->reader->read();

        return isset($configJobs[$group][$jobCode]);
    }

    /**
     * Get job code type (db, xml, db_xml)
     *
     * @param string $jobCode
     * @param string $group
     *
     * @return string
     */
    private function getJobcodeType($jobCode, $group): string
    {
        $xmlJobs = $this->reader->read();
        $dbJobs = $this->dbReader->get();

        $xml = isset($xmlJobs[$group][$jobCode]);
        $db = isset($dbJobs[$group][$jobCode]);
        if ($xml && $db) {
            $result = self::CRON_DB_XML;
        } elseif (!$xml && $db) {
            $result = self::CRON_DB;
        } elseif ($xml && !$db) {
            $result = self::CRON_XML;
        } else {
            $result = self::CRON_OTHER;
        }

        return $result;
    }
}
