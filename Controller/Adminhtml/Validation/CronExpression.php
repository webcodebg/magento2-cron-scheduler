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

class CronExpression extends Action
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
            $exprArray = explode(',', (string) ($data['expression'] ?? ''));
            $resultJson = $this->resultFactory->create(ResultFactory::TYPE_JSON);
            $result = true;
            foreach ($exprArray as $expr) {
                if (!empty($expr)) {
                    $e = preg_split('#\s+#', $expr, -1, PREG_SPLIT_NO_EMPTY);
                    if (count($e) < 5 || count($e) > 6) {
                        $result = false;
                        break;
                    }

                    if (!preg_match(
                        '/^(\*|([0-9]|1[0-9]|2[0-9]|3[0-9]|4[0-9]|5[0-9])|\*\/([0-9]|1[0-9]|2[0-9]|3[0-9]|4[0-9]|5[0-9])) (\*|([0-9]|1[0-9]|2[0-3])|\*\/([0-9]|1[0-9]|2[0-3])) (\*|([1-9]|1[0-9]|2[0-9]|3[0-1])|\*\/([1-9]|1[0-9]|2[0-9]|3[0-1])) (\*|([1-9]|1[0-2])|\*\/([1-9]|1[0-2])) (\*|([0-6])|\*\/([0-6]))$/
',
                        $expr
                    )) {
                        $result = false;
                        break;
                    }
                }
            }

            return $resultJson->setData(['success' => $result]);
        }

        return $this->_redirect('*/*/listing');
    }
}
