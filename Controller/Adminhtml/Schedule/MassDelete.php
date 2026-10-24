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
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Ui\Component\MassAction\Filter;
use Webcode\CronScheduler\Model\ResourceModel\Schedule\CollectionFactory;

class MassDelete extends Action implements HttpPostActionInterface
{
    /**
     * @var Filter
     */
    protected $filter;

    /**
     * @var string
     */
    protected $aclResource = "schedule_massdelete";

    /**
     * Class constructor.
     *
     * @param Context $context
     * @param CollectionFactory $scheduleCollectionFactory
     * @param Filter $filter
     */
    public function __construct(
        Context $context,
        private readonly CollectionFactory $scheduleCollectionFactory,
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
     */
    public function execute()
    {
        try {
            $collection = $this->filter->getCollection($this->scheduleCollectionFactory->create());
            $collectionSize = $collection->getSize();

            foreach ($collection as $job) {
                $job->delete();
            }

            $this->messageManager->addSuccessMessage(__('A total of %1 record(s) have been deleted.', $collectionSize));

            return $this->_redirect('*/*/listing');
        } catch (Exception $e) {
            $this->messageManager->addErrorMessage($e->getMessage());

            return $this->_redirect('*/*/listing');
        }
    }
}
