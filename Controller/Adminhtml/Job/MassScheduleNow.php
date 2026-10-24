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
use Magento\Cron\Model\Schedule;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Webcode\CronScheduler\Service\Cronjob;
use Webcode\CronScheduler\Model\ResourceModel\Schedule\CollectionFactory;

class MassScheduleNow extends Action implements HttpPostActionInterface
{
    /**
     * @var string
     */
    protected $aclResource = "job_massschedule";

    /**
     * Class constructor.
     *
     * @param Context $context
     * @param CollectionFactory $scheduleCollectionFactory
     * @param TimezoneInterface $timezone
     * @param DateTime $dateTime
     * @param \Webcode\CronScheduler\Service\Schedule $scheduleHelper
     * @param Cronjob $jobHelper
     */
    public function __construct(
        Context $context,
        private readonly CollectionFactory $scheduleCollectionFactory,
        private readonly TimezoneInterface $timezone,
        private readonly DateTime $dateTime,
        private readonly \Webcode\CronScheduler\Service\Schedule $scheduleHelper,
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
     * @return ResponseInterface|ResultInterface
     */
    public function execute()
    {
        $data = $this->getRequest()->getPostValue();

        if (isset($data['selected'])) {
            $jobCodes = $data['selected'];
        } elseif (!isset($data['selected']) && isset($data['excluded'])) {
            $filters = $data['filters'];
            unset($filters['placeholder']);
            $jobCodes = $this->jobHelper->getAllFilterJobCodes($filters);
        }

        if (empty($jobCodes)) {
            $this->messageManager->addErrorMessage(__('Selected jobs can not be scheduled now.'));

            return $this->_redirect('*/*/listing');
        }

        try {
            foreach ($jobCodes as $jobCode) {
                $job_status = $this->jobHelper->isJobActive($jobCode);
                if ($job_status) {
                    $collection = $this->scheduleCollectionFactory->create()->getNewEmptyItem();
                    $now = gmdate('Y-m-d H:i:s', $this->dateTime->gmtTimestamp());
                    $collection->setData('job_code', $jobCode);
                    $collection->setData('status', Schedule::STATUS_PENDING);
                    $collection->setData('created_at', $now);
                    $collection->setData('scheduled_at', $this->scheduleHelper->filterTimeInput());
                    $collection->save();
                    $success[] = $jobCode;
                }
            }
        } catch (Exception $e) {
            $this->messageManager->addErrorMessage($e->getMessage());

            return $this->_redirect('*/*/listing');
        }

        if (isset($success) && !empty($success)) {
            $this->messageManager->addSuccessMessage(__('You scheduled selected jobs now.'));
        }

        return $this->_redirect('*/*/listing');
    }
}
