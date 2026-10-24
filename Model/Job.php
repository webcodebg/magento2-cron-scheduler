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

use Magento\Framework\App\Config\ConfigResource\ConfigInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;

class Job extends AbstractModel
{
    /**
     * @var string
     */
    private string $cronExprTemplate = 'crontab/{$group}/jobs/{$jobcode}/schedule/cron_expr';

    /**
     * @var string
     */
    private string $cronModelTemplate = 'crontab/{$group}/jobs/{$jobcode}/run/model';

    /**
     * @var string
     */
    private string $cronStatusTemplate = 'crontab/{$group}/jobs/{$jobcode}/is_active';

    /**
     * @var string
     */
    private string $scope = ScopeConfigInterface::SCOPE_TYPE_DEFAULT;

    /**
     * class constructor
     *
     * @param Context $context
     * @param Registry $registry
     * @param ConfigInterface $configInterface
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        private readonly ConfigInterface $configInterface,
        ?AbstractResource $resource = null,
        ?AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct($context, $registry, $resource, $resourceCollection, $data);
    }

    /**
     * Save cron job for given data and expression
     *
     * @param $data
     * @param $cronExpr
     * @param $jobCode
     */
    public function saveJob($data, $cronExpr, $jobCode)
    {
        #Cron Expression
        $vars = [
            '{$group}' => $data['group'],
            '{$jobcode}' => $jobCode
        ];

        $cronExprString = strtr($this->cronExprTemplate, $vars);
        $cronModelString = strtr($this->cronModelTemplate, $vars);
        $cronStatusString = strtr($this->cronStatusTemplate, $vars);
        $cronModelValue = $data['instance'] . "::" . $data['method'];
        $cronStatusValue = $data['is_active'];

        $this->configInterface
            ->saveConfig($cronExprString, $cronExpr, $this->scope, 0);
        $this->configInterface
            ->saveConfig($cronModelString, $cronModelValue, $this->scope, 0);
        $this->configInterface
            ->saveConfig($cronStatusString, $cronStatusValue, $this->scope, 0);

        if (array_key_exists('max_runtime', $data)) {
            $this->saveMaxRuntime((string)$jobCode, (string)$data['max_runtime']);
        }
    }

    /**
     * Save the job's own kill limit; anything but digits removes it, so the global limit applies.
     *
     * @param string $jobCode
     * @param string $minutes
     *
     * @return void
     */
    private function saveMaxRuntime(string $jobCode, string $minutes): void
    {
        $path = MaxRuntime::jobPath($jobCode);
        $minutes = trim($minutes);

        if (!ctype_digit($minutes)) {
            $this->configInterface->deleteConfig($path, $this->scope, 0);

            return;
        }

        $this->configInterface->saveConfig($path, $minutes, $this->scope, 0);
    }

    /**
     * Delete the job
     *
     * @param $group
     * @param $jobCode
     */
    public function deleteJob($group, $jobCode)
    {
        $vars = [
            '{$group}' => $group,
            '{$jobcode}' => $jobCode
        ];
        $cronExprString = strtr($this->cronExprTemplate, $vars);
        $cronModelString = strtr($this->cronModelTemplate, $vars);
        $cronStatusString = strtr($this->cronStatusTemplate, $vars);

        $this->configInterface
            ->deleteConfig($cronExprString, $this->scope, 0);
        $this->configInterface
            ->deleteConfig($cronModelString, $this->scope, 0);
        $this->configInterface
            ->deleteConfig($cronStatusString, $this->scope, 0);
        $this->configInterface
            ->deleteConfig(MaxRuntime::jobPath((string)$jobCode), $this->scope, 0);
    }

    /**
     * Change job Status
     *
     * @param $jobData
     * @param $status
     */
    public function changeJobStatus($jobData, $status)
    {
        $vars = [
            '{$group}' => $jobData['group'],
            '{$jobcode}' => $jobData['code']
        ];
        $cronStatusString = strtr($this->cronStatusTemplate, $vars);
        $cronStatusValue = $status;

        $this->configInterface
            ->saveConfig($cronStatusString, $cronStatusValue, $this->scope, 0);
    }
}
