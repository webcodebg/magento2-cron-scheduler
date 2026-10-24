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

class Save extends Action implements HttpPostActionInterface
{
    /**
     * Class constructor.
     *
     * @param Context $context
     * @param TypeListInterface $cacheTypeList
     * @param Cronjob $jobHelper
     * @param Job $jobModel
     */
    public function __construct(
        Context $context,
        private readonly TypeListInterface $cacheTypeList,
        private readonly Cronjob $jobHelper,
        private readonly Job $jobModel
    ) {
        parent::__construct($context);
    }

    /**
     * Is action allowed?
     *
     * Save backs both the add and edit forms, so either granular resource grants it
     * (both are strictly narrower than the previous Magento_Backend::admin fallback).
     *
     * @return bool
     */
    protected function _isAllowed(): bool
    {
        return $this->_authorization->isAllowed('Webcode_CronScheduler::job_addnewjob')
            || $this->_authorization->isAllowed('Webcode_CronScheduler::job_editjob');
    }

    /**
     * Execute action
     *
     * @return ResponseInterface
     */
    public function execute()
    {
        $data = $this->getRequest()->getPostValue();

        try {
            if ($data) {
                $data = $this->jobHelper->trimArray($data);

                if (!$this->isValidJobData($data)) {
                    $this->messageManager->addErrorMessage(
                        __('Invalid cron job: the class/method does not exist or the code/group is malformed.')
                    );

                    return $this->_redirect('*/*/listing');
                }
                #Cron Expression Array
                $cronExprArray = $this->jobHelper->trimArray(explode(',', $data['schedule']));
                $jobData = $this->jobHelper->getJobData();
                $flagMultipleExpression = false;

                #check is multiple expression
                if (count($cronExprArray) > 1) {
                    $flagMultipleExpression = true;
                    $counter = 1;
                }
                foreach ($cronExprArray as $cronExprKey => $cronExpr) {
                    $cronExistResponse = $this->jobHelper->checkIfCronExists($jobData, $cronExpr, $data);

                    #skip the row if already exist.
                    if ($cronExistResponse) {
                        $error[] = $cronExpr;
                        if ($data['mode'] == "edit" && $cronExprKey == 0) {
                            $error = $this->popElement($data, $error, $cronExpr);
                        }
                        continue;
                    }

                    #check the mode
                    if ($flagMultipleExpression
                        && (($data['mode'] == "edit" && $cronExprKey != 0)
                            || ($data['mode']
                                == "add"))) {
                        $result = $this->jobHelper->getCronJobName($jobData, $data['code'], $counter);
                        $jobcode = $result['jobcode'];
                        $counter = $result['counter'];
                    } else {
                        $jobcode = $data['code'];
                    }

                    $this->jobModel->saveJob($data, $cronExpr, $jobcode);
                    $sucess[] = $cronExpr;
                }

                $this->cacheTypeList->cleanType('config');
                if (isset($sucess) && !empty($sucess)) {
                    $this->messageManager->addSuccessMessage(
                        __('You saved the cron job for expressions - ' . join(',', $sucess))
                    );
                }
                if (isset($error) && !empty($error)) {
                    $this->messageManager->addWarningMessage(
                        __('The cron already exists for expressions - ' . join(',', $error))
                    );
                }
            }
        } catch (Exception $e) {
            $this->messageManager->addErrorMessage($e->getMessage());

            return $this->_redirect('*/*/listing');
        }

        return $this->_redirect('*/*/listing');
    }

    /**
     * Server-side validation of the posted job definition.
     *
     * The saved callback (instance::method) is later executed by cron, so the
     * class/method must be revalidated here regardless of client-side checks,
     * and group/code must be safe to interpolate into a config path.
     *
     * @param array $data
     *
     * @return bool
     */
    private function isValidJobData(array $data): bool
    {
        foreach (['instance', 'method', 'group', 'code', 'schedule'] as $key) {
            if (empty($data[$key])) {
                return false;
            }
        }

        if (!preg_match('/^[a-zA-Z0-9_]+$/', $data['group'])
            || !preg_match('/^[a-zA-Z0-9_]+$/', $data['code'])
        ) {
            return false;
        }

        return class_exists($data['instance']) && method_exists($data['instance'], $data['method']);
    }

    /**
     * Pop last element from array
     *
     * @param $data
     * @param $error
     * @param $cronExpr
     *
     * @return mixed
     */
    private function popElement($data, $error, $cronExpr): mixed
    {
        if (isset($data['oldexpressionvalue']) && $cronExpr == $data['oldexpressionvalue']) {
            array_pop($error);
        }

        return $error;
    }
}
