<?php
/*
 * @package      Webcode_CronScheduler
 *
 * @author       Kostadin Bashev (bashev@webcode.bg)
 * @copyright Copyright © 2020–2026 Webcode (https://webcode.bg/)
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License 3.0 (OSL-3.0)
 */

namespace Webcode\CronScheduler\Controller\Adminhtml\Job;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\ResponseInterface;
use Webcode\CronScheduler\Service\Cronjob;
use Webcode\CronScheduler\Model\Job;
use Webcode\CronScheduler\Model\ResourceModel\Schedule\CollectionFactory;

/**
 * Class Delete
 *
 * @package Webcode\CronScheduler\Controller\Adminhtml\Job
 */
class Delete extends Action
{
    /**
     * @var string
     */
    protected $aclResource = "job_deletejob";

    /**
     * Class constructor
     *
     * @param Context $context
     * @param TypeListInterface $cacheTypeList
     * @param CollectionFactory $scheduleCollectionFactory
     * @param Cronjob $jobHelper
     * @param Job $jobModel
     */
    public function __construct(
        Context $context,
        private readonly TypeListInterface $cacheTypeList,
        private readonly CollectionFactory $scheduleCollectionFactory,
        private readonly Cronjob $jobHelper,
        private readonly Job $jobModel
    ) {
        parent::__construct($context);
    }

    /**
     * Is action allowed?
     *
     * @return boolean
     */
    protected function _isAllowed(): bool
    {
        return $this->_authorization->isAllowed('Webcode_CronScheduler::' . $this->aclResource);
    }

    /**
     * Execute action
     *
     * @return ResponseInterface
     */
    public function execute(): ResponseInterface
    {
        $jobcode = $this->getRequest()->getParam('job_code');
        $group = $this->getRequest()->getParam('group');

        if (!empty($jobcode) && !empty($group)) {
            if ($this->jobHelper->isXMLJobcode($jobcode, $group)) {
                $this->messageManager->addErrorMessage(__('The cron job can not be deleted.'));
            } else {
                $collection = $this->scheduleCollectionFactory->create();
                $collection->addFieldToFilter('job_code', $jobcode);

                foreach ($collection as $job) {
                    $job->delete();
                }

                $this->jobModel->deleteJob($group, $jobcode);

                $this->cacheTypeList->cleanType('config');
                $this->messageManager->addSuccessMessage(__('A total of 1 record(s) have been deleted.'));
            }
        }

        return $this->_redirect('*/*/listing');
    }
}
