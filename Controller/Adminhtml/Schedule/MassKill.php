<?php
/*
 * @package      Webcode_CronScheduler
 *
 * @author       Kostadin Bashev (bashev@webcode.bg)
 * @copyright Copyright © 2020–2026 Webcode (https://webcode.bg/)
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License 3.0 (OSL-3.0)
 */

namespace Webcode\CronScheduler\Controller\Adminhtml\Schedule;

use Exception;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Cron\Model\Schedule;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Ui\Component\MassAction\Filter;
use Webcode\CronScheduler\Model\ResourceModel\Schedule\CollectionFactory;

/**
 * Class MassKill
 */
class MassKill extends Action implements HttpPostActionInterface
{
    /**
     * Constant for status killed
     */
    private const STATUS_KILLED = 'killed';

    /**
     * @var Filter
     */
    protected $filter;

    /**
     * @var string
     */
    protected $aclResource = "schedule_masskill";

    /**
     * Class constructor.
     *
     * @param Context $context
     * @param CollectionFactory $scheduleCollectionFactory
     * @param DateTime $dateTime
     * @param Filter $filter
     */
    public function __construct(
        Context $context,
        private readonly CollectionFactory $scheduleCollectionFactory,
        private readonly DateTime $dateTime,
        Filter $filter
    ) {
        $this->filter = $filter;
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
     * @throws LocalizedException
     */
    public function execute()
    {
        $data = $this->getRequest()->getPostValue();

        if (!isset($data['selected']) && isset($data['excluded'])) {
            $collection = $this->filter->getCollection($this->scheduleCollectionFactory->create());
            $ids = $collection->getAllIds();
        } else {
            $collection = $this->scheduleCollectionFactory->create();
            $ids = $data['selected'];
        }

        if (empty($ids)) {
            $this->messageManager->addErrorMessage(__('Selected jobs can not be killed.'));

            return $this->_redirect('*/*/listing');
        }

        try {
            $collection->addFieldToFilter('status', Schedule::STATUS_RUNNING)
                ->addFieldToFilter(
                    'finished_at',
                    ['null' => true]
                )
                ->addFieldToFilter(
                    'schedule_id',
                    ['in' => $ids]
                )
                ->addFieldToSelect(['schedule_id', 'pid'])
                ->load();

            $killedJobData = $collection->getData();
            $killedScheduleIds = $errorScheduleIds = [];
            $runningJobs = array_column($killedJobData, 'schedule_id');
            $errorScheduleIds = array_diff($ids, $runningJobs);

            foreach ($collection as $dbrunningjobs) {
                $pid = $dbrunningjobs->getPid();
                $scheduleId = $dbrunningjobs->getScheduleId();

                if (function_exists('posix_getsid') && posix_getsid($pid) === false) {
                    $errorScheduleIds[] = $scheduleId;
                } else {
                    $finished_at = $this->dateTime->gmtDate('Y-m-d H:i:s');
                    $dbrunningjobs->setData('status', self::STATUS_KILLED);
                    $dbrunningjobs->setData('messages', __('It is killed by admin.'));
                    $dbrunningjobs->setData('finished_at', $finished_at);
                    $dbrunningjobs->save();

                    posix_kill($pid, 9);
                    $killedScheduleIds[] = $scheduleId;
                }
            }
            if (!empty($killedScheduleIds)) {
                $this->messageManager->addSuccessMessage(
                    __(
                        'A total of %1 record(s) have been killed - ' . join(',', $killedScheduleIds),
                        count($killedScheduleIds)
                    )
                );
            }
            if (!empty($errorScheduleIds)) {
                $this->messageManager->addErrorMessage(
                    __(
                        'A total of %1 record(s) can not be killed - ' . join(',', $errorScheduleIds),
                        count($errorScheduleIds)
                    )
                );
            }

            return $this->_redirect('*/*/listing');
        } catch (Exception $e) {
            $this->messageManager->addErrorMessage($e->getMessage());

            return $this->_redirect('*/*/listing');
        }
    }
}
