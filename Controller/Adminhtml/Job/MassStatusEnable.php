<?php
/*
 * @package      Webcode_CronScheduler
 *
 * @author       Kostadin Bashev (bashev@webcode.bg)
 * @copyright Copyright © 2020–2026 Webcode (https://webcode.bg/)
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License 3.0 (OSL-3.0)
 */

namespace Webcode\CronScheduler\Controller\Adminhtml\Job;

use Exception;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\ResponseInterface;
use Webcode\CronScheduler\Service\Cronjob;
use Webcode\CronScheduler\Model\Job;

class MassStatusEnable extends Action implements HttpPostActionInterface
{
    /**
     * @var string
     */
    protected $aclResource = "job_massstatuschange";

    /**
     * Cron job disable status
     */
    const CRON_JOB_ENABLE_STATUS = 1;

    /**
     * Class constructor.
     *
     * @param Context $context
     * @param Job $jobModel
     * @param TypeListInterface $cacheTypeList
     * @param Cronjob $jobHelper
     */
    public function __construct(
        Context $context,
        private readonly Job $jobModel,
        private readonly TypeListInterface $cacheTypeList,
        private readonly Cronjob $jobHelper
    ) {
        parent::__construct($context);
    }

    /**
     * Is action allowed?
     *
     * @return boolean
     */
    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed('Webcode_CronScheduler::' . $this->aclResource);
    }

    /**
     * Execute action
     *
     * @return ResponseInterface
     */
    public function execute()
    {
        $data = $this->getRequest()->getPostValue();

        if (isset($data['selected'])) {
            $jobCodes = $data['selected'];
        } elseif (isset($data['excluded'])) {
            $filters = $data['filters'];
            unset($filters['placeholder']);
            $jobCodes = $this->jobHelper->getAllFilterJobCodes($filters);
        }

        if (empty($jobCodes)) {
            $this->messageManager->addErrorMessage(__('Selected jobs can not be enabled.'));

            return $this->_redirect('*/*/listing');
        }

        try {
            foreach ($jobCodes as $jobCode) {
                $data = $this->jobHelper->getJobDetail($jobCode);
                $this->jobModel->changeJobStatus($data, self::CRON_JOB_ENABLE_STATUS);
            }
            $this->cacheTypeList->cleanType('config');
            $this->messageManager->addSuccessMessage(__('You enabled selected jobs.'));
        } catch (Exception $e) {
            $this->messageManager->addErrorMessage($e->getMessage());

            return $this->_redirect('*/*/listing');
        }

        return $this->_redirect('*/*/listing');
    }
}
