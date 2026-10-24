<?php
/*
 * @package      Webcode_CronScheduler
 *
 * @author       Kostadin Bashev (bashev@webcode.bg)
 * @copyright Copyright © 2020–2026 Webcode (https://webcode.bg/)
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License 3.0 (OSL-3.0)
 */

declare(strict_types=1);

namespace Webcode\CronScheduler\Block\Adminhtml\Schedule;

use DateTime as PhpDateTime;
use DateTimeInterface;
use DateTimeZone;
use Exception;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Backend\Block\Widget\Button;
use Magento\Cron\Model\Schedule as CronSchedule;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Webcode\CronScheduler\Service\Schedule;
use Webcode\CronScheduler\Model\ResourceModel\Schedule\CollectionFactory;

class Timeline extends Template
{
    /**
     * Tiny width, in seconds, given to a point-like event (pending, missed, failed or
     * sub-second success) so it stays a thin marker instead of ballooning into a wide
     * bar that merges with its neighbours. Its on-screen minimum is enforced in pixels
     * by CSS (.timeline-event min-width), which keeps it visible at any zoom level.
     */
    private const MIN_VISIBLE_SECONDS = 1;

    public function __construct(
        Context $context,
        private readonly DateTime $datetime,
        private readonly Schedule $scheduleHelper,
        private readonly CollectionFactory $collectionFactory,
        private readonly TimezoneInterface $timezone,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Register the page action buttons (rendered top-right via getChildHtml).
     *
     * @return $this
     */
    protected function _prepareLayout()
    {
        parent::_prepareLayout();

        $this->addChild('regenerate_button', Button::class, [
            'label' => __('Regenerate schedules'),
            'class' => 'primary',
            'onclick' => "setLocation('" . $this->getRegenerateUrl() . "')",
        ]);

        $this->addChild('cleanlogs_button', Button::class, [
            'label' => __('Clear past logs'),
            'onclick' => "if (window.confirm('"
                . $this->escapeJs(__('Delete all past cron log records (everything scheduled before now)?'))
                . "')) { setLocation('" . $this->getCleanLogsUrl() . "'); }",
        ]);

        $this->addChild('cronjobs_button', Button::class, [
            'label' => __('Cron Jobs'),
            'onclick' => "setLocation('" . $this->getUrl('cronscheduler/job/listing') . "')",
        ]);

        return $this;
    }

    /**
     * Rendered HTML of the "regenerate schedules" button.
     *
     * @return string
     */
    public function getRegenerateButtonHtml(): string
    {
        return $this->getChildHtml('regenerate_button');
    }

    /**
     * Rendered HTML of the "clean logs" button.
     *
     * @return string
     */
    public function getCleanLogsButtonHtml(): string
    {
        return $this->getChildHtml('cleanlogs_button');
    }

    /**
     * Rendered HTML of the "cron jobs" configuration link button.
     *
     * @return string
     */
    public function getCronJobsButtonHtml(): string
    {
        return $this->getChildHtml('cronjobs_button');
    }

    /**
     * Get the data to construct the timeline.
     *
     * Each row is: [jobCode, status, tooltipHtml, startDateParts, endDateParts, scheduleId],
     * where the two date entries are the integer component lists described on
     * getJsDateParts().
     *
     * @return array
     * @throws Exception
     */
    public function getCronJobData(): array
    {
        $data = [];

        // Only the current calendar day (store timezone). scheduled_at is stored in UTC,
        // so today's local midnight boundaries are converted to UTC for the query.
        $storeTimezone = new DateTimeZone($this->timezone->getConfigTimezone());
        $utc = new DateTimeZone('UTC');
        $dayStart = (new PhpDateTime('now', $storeTimezone))->setTime(0, 0, 0);
        $dayEnd = (clone $dayStart)->modify('+1 day');

        $from = (clone $dayStart)->setTimezone($utc)->format('Y-m-d H:i:s');
        $to = (clone $dayEnd)->setTimezone($utc)->format('Y-m-d H:i:s');

        $schedules = $this->collectionFactory->create();
        $schedules->addFieldToFilter('scheduled_at', ['gteq' => $from]);
        $schedules->addFieldToFilter('scheduled_at', ['lt' => $to]);
        $schedules->getSelect()->order('job_code');

        foreach ($schedules as $schedule) {
            $status = (string) $schedule->getStatus();

            [$startDate, $endDate] = $this->resolveInterval($schedule, $status);

            $start = $startDate->format('Y-m-d H:i:s');
            $end = $endDate->format('Y-m-d H:i:s');

            $data[] = [
                $schedule->getJobCode(),
                $status,
                $this->getToolTip($schedule, $this->getStatusLevel($status), $status),
                $this->getJsDateParts($start),
                $this->getJsDateParts($end),
                $schedule->getScheduleId()
            ];
        }

        return $data;
    }

    /**
     * Resolve the [start, end] interval drawn for a schedule.
     *
     * Only a running job stretches (it grows from its start up to "now"). Every
     * other state is a fixed-width bar anchored at its execution time, or at its
     * scheduled time when it never executed (pending / missed).
     *
     * @param CronSchedule $schedule
     * @param string $status
     *
     * @return DateTimeInterface[]
     * @throws Exception
     */
    private function resolveInterval($schedule, string $status): array
    {
        $executedAt = $schedule->getData('executed_at');
        $finishedAt = $schedule->getData('finished_at');
        $scheduledAt = $schedule->getData('scheduled_at');

        $startSource = $executedAt ?: $scheduledAt;
        $startDate = $this->makeDate($startSource);

        if ($status === CronSchedule::STATUS_RUNNING) {
            return [$startDate, $this->makeDate(null)];
        }

        $endDate = $this->makeDate($finishedAt ?: $startSource);

        // Enforce a minimum visible width for point-like events.
        $minEnd = (clone $startDate)->modify('+' . self::MIN_VISIBLE_SECONDS . ' seconds');
        if ($endDate < $minEnd) {
            $endDate = $minEnd;
        }

        return [$startDate, $endDate];
    }

    /**
     * Build a DateTime, in the store timezone, for a stored cron_schedule value.
     *
     * cron_schedule datetimes are persisted in UTC, so a stored value is parsed as UTC
     * and then shifted into the store timezone for display (aligning the bars with the
     * "current time" marker and the browser-local timeline window).
     *
     * NOTE: do NOT use TimezoneInterface::date() here — passed a string it parses it
     * against the *locale* date pattern, not ISO, which corrupts 'Y-m-d H:i:s' values
     * (e.g. 2026-07-23 was read as 2029-01-15).
     *
     * @param string|null $stored A UTC 'Y-m-d H:i:s' value, or null for "now".
     *
     * @return PhpDateTime
     * @throws Exception
     */
    private function makeDate(?string $stored): PhpDateTime
    {
        $storeTimezone = new DateTimeZone($this->timezone->getConfigTimezone());

        if ($stored === null || $stored === '') {
            return new PhpDateTime('now', $storeTimezone);
        }

        $date = new PhpDateTime($stored, new DateTimeZone('UTC'));
        $date->setTimezone($storeTimezone);

        return $date;
    }

    /**
     * Split a date into the integer arguments of a JS Date constructor.
     *
     * Returns [year, monthIndex, day, hour, minute, second, millisecond]. The template
     * emits these one by one through an (int) cast, so the inline script can never
     * receive anything but digits — the reason a whole "new Date(…)" expression is no
     * longer built here.
     *
     * The component form is also load-bearing, not just a safety measure: the local-time
     * Date constructor is what pins a bar to its store-timezone wall clock whatever the
     * browser's own timezone is. An epoch timestamp would re-interpret it browser-side.
     *
     * @param int|string $date
     *
     * @return int[]
     */
    private function getJsDateParts(int|string $date): array
    {
        // A single formatting call: DateTime::date() resolves $date to the same timestamp
        // on every call, so reading all six components off one rendering is equivalent to
        // (and cheaper than) formatting each of them separately.
        $parts = explode(',', $this->datetime->date('Y,m,d,H,i,s', $date));

        return [
            (int) $parts[0],
            (int) $parts[1] - 1, // JS months are zero-based
            (int) $parts[2],
            (int) $parts[3],
            (int) $parts[4],
            (int) $parts[5],
            0,
        ];
    }

    /**
     * Get Status Level
     *
     * @param string $status
     *
     * @return string
     */
    private function getStatusLevel(string $status): string
    {
        return match ($status) {
            CronSchedule::STATUS_ERROR,
            CronSchedule::STATUS_MISSED => 'major',
            CronSchedule::STATUS_RUNNING => 'running',
            CronSchedule::STATUS_PENDING => 'minor',
            CronSchedule::STATUS_SUCCESS => 'notice',
            default => 'critical',
        };
    }

    /**
     * Get tooltip text for each cron job
     *
     * @param CronSchedule $schedule
     * @param string $level
     * @param string $status
     *
     * @return string
     */
    private function getToolTip($schedule, string $level, string $status): string
    {
        $executedAt = $schedule->getData('executed_at');
        $finishedAt = $schedule->getData('finished_at');

        $rows = [
            'Id' => (string) $schedule->getId(),
            'Status' => "<span class='grid-severity-" . $this->escapeHtmlAttr($level) . "'>"
                . $this->escapeHtml($status) . "</span>",
            'Created at' => $this->formatStoredDate($schedule->getData('created_at')),
            'Scheduled at' => $this->formatStoredDate($schedule->getData('scheduled_at')),
            'Executed at' => $this->formatStoredDate($executedAt),
            'Finished at' => $this->formatStoredDate($finishedAt),
        ];

        if ($status === CronSchedule::STATUS_SUCCESS && $executedAt && $finishedAt) {
            $rows['CPU Usage'] = (string) $schedule->getCpuUsage();
            $rows['System Usage'] = (string) $schedule->getSystemUsage();
            $rows['Memory Usage'] = (string) $schedule->getMemoryUsage();
            $rows['Total Executed Time'] = (string) (strtotime($finishedAt) - strtotime($executedAt));
        }

        $tooltip = "<table>"
            . "<tr><td colspan='2'>" . $this->escapeHtml($schedule->getJobCode()) . "</td></tr>";
        foreach ($rows as $label => $value) {
            // Status is pre-built markup; all other values are already escaped above.
            $tooltip .= "<tr><td>" . $this->escapeHtml((string) $label) . "</td><td>" . $value . "</td></tr>";
        }
        $tooltip .= "</table>";

        return $tooltip;
    }

    /**
     * Format a stored (UTC) datetime into a store-timezone display string.
     *
     * @param string|null $date
     *
     * @return string
     * @throws Exception
     */
    private function formatStoredDate(?string $date): string
    {
        if (empty($date)) {
            return '';
        }

        return $this->escapeHtml($this->makeDate($date)->format('Y-m-d H:i:s'));
    }

    /**
     * Integer Date constructor arguments for the "current time" marker.
     *
     * Same shape as getJsDateParts(), so the template renders both through one helper.
     *
     * @return int[]
     */
    public function getCurrentDateParts(): array
    {
        $current = $this->datetime->date('U') + $this->datetime->getGmtOffSet('seconds');

        return $this->getJsDateParts($current);
    }

    /**
     * URL to edit a single cron job's configuration.
     *
     * @param string $jobCode
     *
     * @return string
     */
    public function getJobEditUrl(string $jobCode): string
    {
        return $this->getUrl('cronscheduler/job/edit', ['job_code' => $jobCode]);
    }

    /**
     * URL of the "regenerate schedules" admin action.
     *
     * @return string
     */
    public function getRegenerateUrl(): string
    {
        return $this->getUrl('cronscheduler/schedule/regenerate');
    }

    /**
     * URL of the "clean logs older than 24h" admin action.
     *
     * @return string
     */
    public function getCleanLogsUrl(): string
    {
        return $this->getUrl('cronscheduler/schedule/cleanlogs');
    }
}
