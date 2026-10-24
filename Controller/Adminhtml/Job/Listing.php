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
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Webcode\CronScheduler\Service\Schedule;

class Listing extends Action
{
    /**
     * @var string
     */
    protected $aclResource = "job_listing";

    /**
     * Class constructor.
     *
     * @param Context $context
     * @param Schedule $scheduleHelper
     */
    public function __construct(
        Context $context,
        private readonly Schedule $scheduleHelper
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
     * @return ResultInterface
     */
    public function execute()
    {
        $this->scheduleHelper->getLastCronStatusMessage();
        $resultPage = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        $resultPage->setActiveMenu("Magento_Backend::system");
        $resultPage->getConfig()->getTitle()->prepend(__('Cron Jobs'));
        $resultPage->addBreadcrumb(__('Cron Scheduler'), __('Cron Scheduler'));

        return $resultPage;
    }
}
