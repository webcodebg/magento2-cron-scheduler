<?php
/*
 * @package      Webcode_CronScheduler
 *
 * @author       Kostadin Bashev (bashev@webcode.bg)
 * @copyright Copyright © 2020–2026 Webcode (https://webcode.bg/)
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License 3.0 (OSL-3.0)
 */

namespace Webcode\CronScheduler\Controller\Adminhtml\Validation;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;

class MethodExistance extends Action
{
    /**
     * @var string
     */
    protected $aclResource = 'job_addnewjob';

    /**
     * Class constructor
     *
     * @param Context $context
     */
    public function __construct(
        Context $context
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
     * @return ResponseInterface|ResultInterface
     */
    public function execute()
    {
        $request = $this->getRequest();
        if ($request instanceof HttpRequest && $request->isXmlHttpRequest()) {
            $data = $request->getPostValue();
            $classpath = trim((string) ($data['classpath'] ?? ''));
            $methodname = trim((string) ($data['methodname'] ?? ''));
            $resultJson = $this->resultFactory->create(ResultFactory::TYPE_JSON);
            $result = false;

            if (!empty($classpath)) {
                if (method_exists($classpath, $methodname)) {
                    $result = true;
                }
            }

            return $resultJson->setData(['success' => $result]);
        }

        return $this->_redirect('*/*/listing');
    }
}
