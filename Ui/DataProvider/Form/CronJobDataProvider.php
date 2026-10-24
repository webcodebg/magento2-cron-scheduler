<?php
/*
 * @package      Webcode_CronScheduler
 *
 * @author       Kostadin Bashev (bashev@webcode.bg)
 * @copyright Copyright © 2020–2026 Webcode (https://webcode.bg/)
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License 3.0 (OSL-3.0)
 */

declare(strict_types=1);

namespace Webcode\CronScheduler\Ui\DataProvider\Form;

use Magento\Cron\Model\ResourceModel\Schedule\CollectionFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Ui\DataProvider\AbstractDataProvider;
use Magento\Framework\App\RequestInterface;
use Webcode\CronScheduler\Model\MaxRuntime;
use Webcode\CronScheduler\Service\Cronjob;

class CronJobDataProvider extends AbstractDataProvider
{
    /**
     * @var array|null
     */
    private ?array $loadedData = null;

    /**
     * Class constructor.
     * @param string $name
     * @param string $primaryFieldName
     * @param string $requestFieldName
     * @param CollectionFactory $collectionFactory
     * @param Cronjob $jobHelper
     * @param ScopeConfigInterface $scopeConfig
     * @param RequestInterface $request
     * @param array $meta
     * @param array $data
     */
    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        CollectionFactory $collectionFactory,
        private readonly Cronjob $jobHelper,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly RequestInterface $request,
        array $meta = [],
        array $data = []
    ) {
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
        $this->collection = $collectionFactory->create();
    }

    /**
     * {@inheritdoc}
     * @return array
     */
    public function getData()
    {
        if ($this->loadedData) {
            return $this->loadedData;
        }

        $this->loadedData = $this->jobHelper->getJobData();
        $jobCode = $this->request->getParam('job_code');
        if (!empty($jobCode)) {
            if (isset($this->loadedData[$jobCode])) {
                if (isset($this->loadedData[$jobCode]['config_path']) && $configPath = $this->loadedData[$jobCode]['config_path']) {
                    $this->loadedData[$jobCode]['schedule'] = $this->scopeConfig->getValue($configPath);
                }

                $this->loadedData[$jobCode]['oldexpressionvalue'] = $this->loadedData[$jobCode]['schedule'];
                $this->loadedData[$jobCode]['max_runtime'] = $this->scopeConfig->getValue(
                    MaxRuntime::jobPath((string)$jobCode)
                );
            }
        }

        return $this->loadedData;
    }
}
