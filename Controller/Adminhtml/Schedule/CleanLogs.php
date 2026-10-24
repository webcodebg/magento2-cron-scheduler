<?php
/*
 * @package      Webcode_CronScheduler
 *
 * @author       Kostadin Bashev (bashev@webcode.bg)
 * @copyright Copyright © 2020–2026 Webcode (https://webcode.bg/)
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License 3.0 (OSL-3.0)
 */

declare(strict_types=1);

namespace Webcode\CronScheduler\Controller\Adminhtml\Schedule;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Cron\Model\ResourceModel\Schedule as ScheduleResource;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Throwable;

/**
 * Deletes every past cron_schedule row (scheduled before "now"), i.e. all the already
 * finished/missed/errored logs, keeping only current and upcoming (pending) schedules.
 * Triggered manually, so pruning the whole history loses nothing that still matters.
 */
class CleanLogs extends Action
{
    /**
     * @var string
     */
    protected $aclResource = "schedule_cleanlogs";

    public function __construct(
        Context $context,
        private readonly ScheduleResource $scheduleResource,
        private readonly DateTime $dateTime
    ) {
        parent::__construct($context);
    }

    /**
     * Is action allowed?
     *
     * @return bool
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
        try {
            // scheduled_at is stored in UTC, so the "now" threshold must be UTC too.
            $threshold = gmdate('Y-m-d H:i:s', $this->dateTime->gmtTimestamp());

            $connection = $this->scheduleResource->getConnection();
            $deleted = $connection->delete(
                $this->scheduleResource->getMainTable(),
                ['scheduled_at < ?' => $threshold]
            );

            $this->messageManager->addSuccessMessage(
                __('%1 past cron log record(s) were removed.', $deleted)
            );
        } catch (Throwable $e) {
            $this->messageManager->addErrorMessage(
                __('Could not clean cron logs: %1', $e->getMessage())
            );
        }

        return $this->_redirect('*/*/timeline');
    }
}
