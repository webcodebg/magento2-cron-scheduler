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
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Process\PhpExecutableFinderFactory;
use Magento\Framework\ShellInterface;
use Throwable;

/**
 * Kicks off a background cron run so schedules are (re)generated and any due jobs
 * are picked up — useful when the cron daemon has stalled and the timeline is empty.
 */
class Regenerate extends Action
{
    /**
     * @var string
     */
    protected $aclResource = "schedule_regenerate";

    public function __construct(
        Context $context,
        private readonly ShellInterface $shell,
        private readonly PhpExecutableFinderFactory $phpExecutableFinderFactory
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
            $phpPath = $this->phpExecutableFinderFactory->create()->find() ?: 'php';
            // The injected shell is Magento_Cron's shellBackgroundCron (see di.xml), whose
            // renderer detaches the process (background + discarded output), so the admin
            // request returns immediately instead of blocking on the whole cron queue.
            $this->shell->execute(
                $phpPath . ' %s cron:run',
                [BP . '/bin/magento']
            );
            $this->messageManager->addSuccessMessage(
                __('Cron run was triggered in the background. Refresh the timeline in a few seconds.')
            );
        } catch (Throwable $e) {
            $this->messageManager->addErrorMessage(
                __('Could not trigger the cron run: %1', $e->getMessage())
            );
        }

        return $this->_redirect('*/*/timeline');
    }
}
