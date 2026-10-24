<?php
/*
 * @package      Webcode_CronScheduler
 *
 * @author       Kostadin Bashev (bashev@webcode.bg)
 * @copyright Copyright © 2020–2026 Webcode (https://webcode.bg/)
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License 3.0 (OSL-3.0)
 */

declare(strict_types=1);

namespace Webcode\CronScheduler\Cron;

use Exception;
use Magento\Cron\Model\Schedule;
use Magento\Framework\App\Area;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Mail\Template\SenderResolverInterface;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\Translate\Inline\StateInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use Webcode\CronScheduler\Model\ResourceModel\Schedule\Collection;
use Webcode\CronScheduler\Model\ResourceModel\Schedule\CollectionFactory;

/**
 * Sendemail responds for sending emails for failed cronjobs.
 */
class Sendemail
{
    /**
     * Recipient email config path
     */
    const XML_PATH_EMAIL_RECIPIENT = 'cronscheduler/general/cronscheduler_admin_email';

    /**
     * Recipient email enable/disable status
     */
    const XML_PATH_EMAIL_ENABLE_STATUS = 'cronscheduler/general/cronscheduler_email_enabled';

    /**
     * Test Email Template Name
     */
    const TEST_EMAIL_TEMPLATE = 'cronscheduler_email_template';

    /**
     * Test Email Template Name
     */
    const IS_MAIL_STATUS = 1;

    public function __construct(
        private readonly CollectionFactory $scheduleCollectionFactory,
        private readonly TransportBuilder $transportBuilder,
        private readonly StateInterface $inlineTranslation,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly DateTime $dateTime,
        private readonly SenderResolverInterface $senderResolver,
        private readonly StoreManagerInterface $storeManager,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Execute action
     *
     * @return void
     */
    public function execute(): void
    {
        $emailEnableStatus = $this->scopeConfig
            ->getValue(self::XML_PATH_EMAIL_ENABLE_STATUS, ScopeInterface::SCOPE_STORE);

        if ($emailEnableStatus) {
            $emailItems['errorMessages'] = $this->getFatalErrorOfJobcode();
            $emailItems['missedJobs'] = $this->getMissedCronJob();

            $receiverEmailConfig = $this->scopeConfig
                ->getValue(self::XML_PATH_EMAIL_RECIPIENT, ScopeInterface::SCOPE_STORE);
            $receiverEmailIds = explode(',', $receiverEmailConfig);

            if (!empty($receiverEmailIds)
                && (!empty($emailItems['errorMessages']->getData())
                    || !empty($emailItems['missedJobs']->getData()))) {
                try {
                    $from = $this->senderResolver->resolve('general');

                    $this->sendEmailStatus($receiverEmailIds, $from, $emailItems);
                    $this->updateMailStatus($emailItems);
                } catch (Exception $e) {
                    $this->logger->critical($e);
                }
            }
        }
    }

    /**
     * Update is mail status after sending an email
     *
     * @param $emailItems
     */
    private function updateMailStatus($emailItems): void
    {
        if (!empty($emailItems['errorMessages'])) {
            foreach ($emailItems['errorMessages'] as $errorMessage) {
                $collection = $this->scheduleCollectionFactory->create();
                $filters = [
                    'schedule_id' => $errorMessage['max_id'],
                    'job_code' => $errorMessage['job_code'],
                    'status' => Schedule::STATUS_ERROR
                ];
                $collection->updateMailStatusByJobCode(['is_mail_sent' => self::IS_MAIL_STATUS], $filters);
            }
        }

        if (!empty($emailItems['missedJobs'])) {
            foreach ($emailItems['missedJobs'] as $missedJob) {
                $collection = $this->scheduleCollectionFactory->create();
                $filters = [
                    'schedule_id' => $missedJob['max_id'],
                    'job_code' => $missedJob['job_code'],
                    'status' => Schedule::STATUS_MISSED
                ];
                $collection->updateMailStatusByJobCode(['is_mail_sent' => self::IS_MAIL_STATUS], $filters);
            }
        }
    }

    /**
     * Get Missed cron jobs count
     *
     * @return Collection
     */
    private function getMissedCronJob(): Collection
    {
        $collection = $this->scheduleCollectionFactory->create();
        $collection->getSelect()->where('status = "' . Schedule::STATUS_MISSED . '"')
            ->where('is_mail_sent is NULL')
            ->reset('columns')
            ->columns(['job_code', 'MAX(schedule_id) as max_id', 'COUNT(schedule_id) as totalmissed'])
            ->group(['job_code']);

        return $collection;
    }

    /**
     * Get Each Cron Job Fatal error
     *
     * @return Collection
     */
    private function getFatalErrorOfJobcode(): Collection
    {
        $collection = $this->scheduleCollectionFactory->create();
        $collection->getSelect()->where('status = "' . Schedule::STATUS_ERROR . '"')
            ->where('error_message is not NULL')
            ->where('is_mail_sent is NULL')
            ->reset('columns')
            ->columns(['job_code', 'error_message', 'MAX(schedule_id) as max_id'])
            ->group(['job_code']);

        return $collection;
    }

    /**
     * Send Email
     *
     * @param $to
     * @param $from
     * @param $items
     *
     * @return $this
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    private function sendEmailStatus($to, $from, $items): static
    {
        $templateOptions = [
            'area' => Area::AREA_FRONTEND,
            'store' => $this->storeManager->getStore()->getId()
        ];
        $templateVars = [
            'store' => $this->storeManager->getStore(),
            'items' => $items,
        ];

        $this->inlineTranslation->suspend();

        $this->transportBuilder->setTemplateIdentifier(self::TEST_EMAIL_TEMPLATE)
            ->setTemplateOptions($templateOptions)
            ->setTemplateVars($templateVars)
            ->setFrom($from)
            ->addTo($to);

        $transport = $this->transportBuilder->getTransport();
        $transport->sendMessage();

        $this->inlineTranslation->resume();

        return $this;
    }
}
