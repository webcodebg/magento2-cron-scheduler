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
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;

/**
 * Class Edit
 *
 * @package Webcode\CronScheduler\Controller\Adminhtml\Job
 */
class Edit extends Action
{
    /**
     * @var string
     */
    protected $aclResource = "job_editjob";

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
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        $resultPage = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        $resultPage->setActiveMenu("Magento_Backend::system");
        $resultPage->getConfig()->getTitle()->prepend(__('Edit Cron Job'));
        $resultPage->addBreadcrumb(__('Cron Scheduler'), __('Cron Scheduler'));

        return $resultPage;
    }
}
