<?php
/*
 * @package      Webcode_CronScheduler
 *
 * @author       Kostadin Bashev (bashev@webcode.bg)
 * @copyright Copyright © 2020–2026 Webcode (https://webcode.bg/)
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License 3.0 (OSL-3.0)
 */

namespace Webcode\CronScheduler\Observer;

use Exception;
use Laminas\Http\PhpEnvironment\Request as Environment;
use Magento\Cron\Model\ConfigInterface;
use Magento\Cron\Model\DeadlockRetrierInterface;
use Magento\Cron\Model\ResourceModel\Schedule\Collection;
use Magento\Cron\Model\Schedule;
use Magento\Cron\Model\ScheduleFactory;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Console\Request;
use Magento\Framework\App\State;
use Magento\Framework\Console\Cli;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Lock\LockManagerInterface;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Process\PhpExecutableFinderFactory;
use Magento\Framework\Profiler\Driver\Standard\Stat;
use Magento\Framework\Profiler\Driver\Standard\StatFactory;
use Magento\Framework\ShellInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Store\Model\ScopeInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;
use Webcode\CronScheduler\Service\Cronjob;

/**
 * Deliberately installed via a <preference> for the core cron observer (see etc/di.xml).
 *
 * The extra bookkeeping this module needs — capturing pid / CPU / memory usage and the
 * error message/file/line of a crashed job — hooks into cron internals that core exposes
 * only through private methods (processPendingJobs(), _runJob()). Those cannot be reached
 * with a plugin, so subclassing + preference is the only viable extension point.
 *
 * COUPLING: the constructor below mirrors the parent signature 1:1 and forwards every
 * argument to parent::__construct(). If Magento changes Magento\Cron\Observer\
 * ProcessCronQueueObserver's constructor, this signature and the forwarding call MUST be
 * updated in lock-step, otherwise cron will fatal on instantiation.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.TooManyFields)
 */
class ProcessCronQueueObserver extends \Magento\Cron\Observer\ProcessCronQueueObserver
{
    /**
     * @var array
     */
    private $invalid = [];
    /**
     * Schedule currently being executed, exposed so the shutdown handler can flag
     * it when a fatal (uncatchable) error kills the process mid-run.
     *
     * @var Schedule|null
     */
    private ?Schedule $runningSchedule = null;
    /**
     * @var Cronjob
     */
    private $jobHelper = null;
    /**
     * @var LockManagerInterface
     */
    private $lockManager;
    /**
     * @var LoggerInterface
     */
    private $logger;
    /**
     * @var \Webcode\CronScheduler\Service\Schedule
     */
    private $scheduleHelper = null;
    /**
     * @var Stat
     */
    private $statProfiler;
    /**
     * @var State
     */
    private $state;

    /**
     * @param ObjectManagerInterface $objectManager
     * @param ScheduleFactory $scheduleFactory
     * @param CacheInterface $cache
     * @param ConfigInterface $config
     * @param ScopeConfigInterface $scopeConfig
     * @param Request $request
     * @param ShellInterface $shell
     * @param DateTime $dateTime
     * @param PhpExecutableFinderFactory $phpExecutableFinderFactory
     * @param LoggerInterface $logger
     * @param State $state
     * @param StatFactory $statFactory
     * @param LockManagerInterface $lockManager
     * @param ManagerInterface $eventManager
     * @param DeadlockRetrierInterface $retrier
     * @param \Webcode\CronScheduler\Service\Schedule $scheduleHelper
     * @param Cronjob $jobHelper
     */
    public function __construct(
        ObjectManagerInterface             $objectManager,
        ScheduleFactory                   $scheduleFactory,
        CacheInterface                 $cache,
        ConfigInterface                   $config,
        ScopeConfigInterface    $scopeConfig,
        Request                $request,
        ShellInterface                     $shell,
        DateTime           $dateTime,
        PhpExecutableFinderFactory $phpExecutableFinderFactory,
        LoggerInterface                              $logger,
        State                                                 $state,
        StatFactory                                           $statFactory,
        LockManagerInterface                                  $lockManager,
        ManagerInterface                                      $eventManager,
        DeadlockRetrierInterface                              $retrier,
        Environment                                           $environment,
        \Webcode\CronScheduler\Service\Schedule                $scheduleHelper,
        Cronjob                                               $jobHelper
    ) {
        parent::__construct(
            $objectManager,
            $scheduleFactory,
            $cache,
            $config,
            $scopeConfig,
            $request,
            $shell,
            $dateTime,
            $phpExecutableFinderFactory,
            $logger,
            $state,
            $statFactory,
            $lockManager,
            $eventManager,
            $retrier,
            $environment
        );
        $this->logger = $logger;
        $this->state = $state;
        $this->statProfiler = $statFactory->create();
        $this->lockManager = $lockManager;
        $this->scheduleHelper = $scheduleHelper;
        $this->jobHelper = $jobHelper;
    }

    /**
     * Execute job by calling specific class::method
     *
     * @param int $scheduledTime
     * @param int $currentTime
     * @param string[] $jobConfig
     * @param Schedule $schedule
     * @param string $groupId
     *
     * @return void
     * @throws Exception
     */
    protected function _runJob($scheduledTime, $currentTime, $jobConfig, $schedule, $groupId)
    {
        $jobCode = $schedule->getJobCode();
        $scheduleLifetime = $this->getCronGroupConfigurationValue($groupId, self::XML_PATH_SCHEDULE_LIFETIME);
        $scheduleLifetime = $scheduleLifetime * self::SECONDS_IN_MINUTE;
        if ($scheduledTime < $currentTime - $scheduleLifetime) {
            $schedule->setStatus(Schedule::STATUS_MISSED);
            // phpcs:ignore Magento2.Exceptions.DirectThrow
            throw new Exception(sprintf('Cron Job %s is missed at %s', $jobCode, $schedule->getScheduledAt()));
        }

        if (!isset($jobConfig['instance'], $jobConfig['method'])) {
            $schedule->setStatus(Schedule::STATUS_ERROR);
            // phpcs:ignore Magento2.Exceptions.DirectThrow
            throw new Exception(sprintf('No callbacks found for cron job %s', $jobCode));
        }
        $model = $this->_objectManager->create($jobConfig['instance']);
        $callback = [$model, $jobConfig['method']];
        if (!is_callable($callback)) {
            $schedule->setStatus(Schedule::STATUS_ERROR);
            // phpcs:ignore Magento2.Exceptions.DirectThrow
            throw new Exception(
                sprintf('Invalid callback: %s::%s can\'t be called', $jobConfig['instance'], $jobConfig['method'])
            );
        }

        $schedule->setExecutedAt($this->dateTime->gmtTimestamp())->save();

        $this->startProfiling();
        try {
            $this->logger->info(sprintf('Cron Job %s is run', $jobCode));
            //phpcs:ignore Magento2.Functions.DiscouragedFunction
            call_user_func_array($callback, [$schedule]);
        } catch (Throwable $e) {
            $schedule->setStatus(Schedule::STATUS_ERROR);
            $this->logger->error(
                sprintf(
                    'Cron Job %s has an error: %s. Statistics: %s',
                    $jobCode,
                    $e->getMessage(),
                    $this->getProfilingStat()
                )
            );
            if (!$e instanceof Exception) {
                $e = new RuntimeException(
                    'Error when running a cron job: ' . $e->getMessage(),
                    0,
                    $e
                );
            }
            throw $e;
        } finally {
            $this->stopProfiling();
        }

        $schedule->setStatus(Schedule::STATUS_SUCCESS)->setFinishedAt($this->dateTime->gmtTimestamp());

        $this->logger->info(
            sprintf(
                'Cron Job %s is successfully finished. Statistics: %s',
                $jobCode,
                $this->getProfilingStat()
            )
        );
    }

    /**
     * Clean up scheduled jobs that are disabled in the configuration.
     *
     * This can happen when you turn off a cron job in the config and flush the cache.
     *
     * @param string $groupId
     *
     * @return void
     * @throws LocalizedException
     */
    private function cleanupDisabledJobs($groupId): void
    {
        $jobs = $this->_config->getJobs();
        $jobsToCleanup = [];
        foreach ($jobs[$groupId] as $jobCode => $jobConfig) {
            if (!$this->getCronExpression($jobConfig)) {
                /** @var \Magento\Cron\Model\ResourceModel\Schedule $scheduleResource */
                $jobsToCleanup[] = $jobCode;
            }
        }

        if (count($jobsToCleanup) > 0) {
            $scheduleResource = $this->_scheduleFactory->create()->getResource();
            $count = $scheduleResource->getConnection()->delete(
                $scheduleResource->getMainTable(),
                [
                    'status = ?' => Schedule::STATUS_PENDING,
                    'job_code in (?)' => $jobsToCleanup,
                ]
            );

            $this->logger->info(sprintf('%d cron jobs were cleaned', $count));
        }
    }

    /**
     * Clean expired jobs
     *
     * @param string $groupId
     * @param int $currentTime
     *
     * @throws LocalizedException
     */
    private function cleanupJobs($groupId, $currentTime)
    {
        // check if history cleanup is needed
        $lastCleanup = (int)$this->_cache->load(self::CACHE_KEY_LAST_HISTORY_CLEANUP_AT . $groupId);
        $historyCleanUp = (int)$this->getCronGroupConfigurationValue($groupId, self::XML_PATH_HISTORY_CLEANUP_EVERY);
        if ($lastCleanup > $this->dateTime->gmtTimestamp() - $historyCleanUp * self::SECONDS_IN_MINUTE) {
            return $this;
        }
        // save time history cleanup was ran with no expiration
        $this->_cache->save(
            $this->dateTime->gmtTimestamp(),
            self::CACHE_KEY_LAST_HISTORY_CLEANUP_AT . $groupId,
            ['crontab'],
            null
        );

        $this->cleanupDisabledJobs($groupId);

        $historySuccess = (int)$this->getCronGroupConfigurationValue($groupId, self::XML_PATH_HISTORY_SUCCESS);
        $historyFailure = (int)$this->getCronGroupConfigurationValue($groupId, self::XML_PATH_HISTORY_FAILURE);
        $historyLifetimes = [
            Schedule::STATUS_SUCCESS => $historySuccess * self::SECONDS_IN_MINUTE,
            Schedule::STATUS_MISSED => $historyFailure * self::SECONDS_IN_MINUTE,
            Schedule::STATUS_ERROR => $historyFailure * self::SECONDS_IN_MINUTE,
            Schedule::STATUS_PENDING => max($historyFailure, $historySuccess) * self::SECONDS_IN_MINUTE,
        ];

        $jobs = $this->_config->getJobs()[$groupId];
        $scheduleResource = $this->_scheduleFactory->create()->getResource();
        $connection = $scheduleResource->getConnection();
        $count = 0;
        foreach ($historyLifetimes as $status => $time) {
            $count += $connection->delete(
                $scheduleResource->getMainTable(),
                [
                    'status = ?' => $status,
                    'job_code in (?)' => array_keys($jobs),
                    'created_at < ?' => $connection->formatDate($currentTime - $time)
                ]
            );
        }

        if ($count) {
            $this->logger->info(sprintf('%d cron jobs were cleaned', $count));
        }
    }

    /**
     * Clean up scheduled jobs that do not match their cron expression anymore.
     *
     * This can happen when you change the cron expression and flush the cache.
     *
     * @return $this
     * @throws LocalizedException
     */
    private function cleanupScheduleMismatches(): static
    {
        /** @var \Magento\Cron\Model\ResourceModel\Schedule $scheduleResource */
        $scheduleResource = $this->_scheduleFactory->create()->getResource();
        foreach ($this->invalid as $jobCode => $scheduledAtList) {
            $scheduleResource->getConnection()->delete(
                $scheduleResource->getMainTable(),
                [
                    'status = ?' => Schedule::STATUS_PENDING,
                    'job_code = ?' => $jobCode,
                    'scheduled_at in (?)' => $scheduledAtList,
                ]
            );
        }

        return $this;
    }

    /**
     * Process cron queue
     * Generate tasks schedule
     * Cleanup tasks schedule
     *
     * @param Observer $observer
     *
     * @return void
     * @throws LocalizedException|Throwable
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function execute(Observer $observer): void
    {
        register_shutdown_function(function () {
            $errorMessage = error_get_last();
            $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
            if ($this->runningSchedule !== null
                && $errorMessage !== null
                && in_array($errorMessage['type'], $fatalTypes, true)
            ) {
                $schedule = $this->runningSchedule;
                $schedule->setStatus(Schedule::STATUS_ERROR);
                $schedule->setErrorMessage($errorMessage['message']);
                $schedule->setErrorFile($errorMessage['file']);
                $schedule->setErrorLine($errorMessage['line']);
                $schedule->save();
            }
        });

        $currentTime = $this->dateTime->gmtTimestamp();
        $jobGroupsRoot = $this->_config->getJobs();
        // sort jobs groups to start from used in separated process
        uksort(
            $jobGroupsRoot,
            function($a, $b)
            {
                return $this->getCronGroupConfigurationValue($b, 'use_separate_process')
                       - $this->getCronGroupConfigurationValue($a, 'use_separate_process');
            }
        );

        $phpPath = $this->phpExecutableFinder->find() ?: 'php';

        foreach ($jobGroupsRoot as $groupId => $jobsRoot) {
            if (!$this->isGroupInFilter($groupId)) {
                continue;
            }
            if ($this->isGroupInExcludeFilter($groupId)) {
                continue;
            }
            if ($this->_request->getParam(self::STANDALONE_PROCESS_STARTED) !== '1'
                && $this->getCronGroupConfigurationValue($groupId, 'use_separate_process') == 1
            ) {
                $this->_shell->execute(
                    $phpPath . ' %s cron:run --group=' . $groupId . ' --' . Cli::INPUT_KEY_BOOTSTRAP . '='
                    . self::STANDALONE_PROCESS_STARTED . '=1',
                    [
                        BP . '/bin/magento'
                    ]
                );
                continue;
            }

            $this->lockGroup(
                $groupId,
                function($groupId) use ($currentTime, $jobsRoot)
                {
                    $this->cleanupJobs($groupId, $currentTime);
                    $this->generateSchedules($groupId);
                    $this->processPendingJobs($groupId, $jobsRoot, $currentTime);
                }
            );
        }
    }

    /**
     * Generate cron schedule
     *
     * @param string $groupId
     *
     * @return $this
     * @throws LocalizedException
     */
    private function generateSchedules($groupId): static
    {
        /**
         * check if schedule generation is needed
         */
        $lastRun = (int)$this->_cache->load(self::CACHE_KEY_LAST_SCHEDULE_GENERATE_AT . $groupId);
        $rawSchedulePeriod = (int)$this->getCronGroupConfigurationValue(
            $groupId,
            self::XML_PATH_SCHEDULE_GENERATE_EVERY
        );
        $schedulePeriod = $rawSchedulePeriod * self::SECONDS_IN_MINUTE;
        if ($lastRun > $this->dateTime->gmtTimestamp() - $schedulePeriod) {
            return $this;
        }

        /**
         * save time schedules generation was ran with no expiration
         */
        $this->_cache->save(
            $this->dateTime->gmtTimestamp(),
            self::CACHE_KEY_LAST_SCHEDULE_GENERATE_AT . $groupId,
            ['crontab'],
            null
        );

        $schedules = $this->getNonExitedSchedules($groupId);
        $exists = [];
        /** @var Schedule $schedule */
        foreach ($schedules as $schedule) {
            $exists[$schedule->getJobCode() . '/' . $schedule->getScheduledAt()] = 1;
        }
        /**
         * generate global crontab jobs
         */
        $jobs = $this->_config->getJobs();
        $this->invalid = [];
        $this->_generateJobs($jobs[$groupId], $exists, $groupId);
        $this->cleanupScheduleMismatches();

        return $this;
    }

    /**
     * Get cron expression of cron job.
     *
     * @param array $jobConfig
     *
     * @return null|string
     */
    private function getCronExpression($jobConfig): ?string
    {
        $cronExpression = null;
        if (isset($jobConfig['config_path'])) {
            $cronExpression = $this->getConfigSchedule($jobConfig) ?: null;
        }

        if (!$cronExpression) {
            if (isset($jobConfig['schedule'])) {
                $cronExpression = $jobConfig['schedule'];
            }
        }

        return $cronExpression;
    }

    /**
     * Get CronGroup Configuration Value.
     *
     * @param string $groupId
     * @param string $path
     *
     * @return int
     */
    private function getCronGroupConfigurationValue($groupId, $path): int
    {
        return (int)$this->_scopeConfig->getValue(
            'system/cron/' . $groupId . '/' . $path,
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Return job collection from database with status 'pending', 'running' or 'success'
     *
     * @param string $groupId
     *
     * @return AbstractCollection
     */
    private function getNonExitedSchedules($groupId)
    {
        $jobs = $this->_config->getJobs();
        $pendingJobs = $this->_scheduleFactory->create()->getCollection();
        $pendingJobs->addFieldToFilter(
            'status',
            [
                'in' => [
                    Schedule::STATUS_PENDING,
                    Schedule::STATUS_RUNNING,
                    Schedule::STATUS_SUCCESS
                ]
            ]
        );
        $pendingJobs->addFieldToFilter('job_code', ['in' => array_keys($jobs[$groupId])]);

        return $pendingJobs;
    }

    /**
     * Return job collection from data base with status 'pending'.
     *
     * @param string $groupId
     *
     * @return Collection
     */
    private function getPendingSchedules($groupId)
    {
        $jobs = $this->_config->getJobs();
        $pendingJobs = $this->_scheduleFactory->create()->getCollection();
        $pendingJobs->addFieldToFilter('status', Schedule::STATUS_PENDING);
        $pendingJobs->addFieldToFilter('job_code', ['in' => array_keys($jobs[$groupId])]);

        return $pendingJobs;
    }

    /**
     * Retrieves statistics in the JSON format
     *
     * @return string
     */
    private function getProfilingStat()
    {
        $stat = $this->statProfiler->get('job');
        unset($stat[Stat::START]);

        return json_encode($stat);
    }

    /**
     * Is Group In Exclude Filter.
     *
     * Mirrors the core observer: cron:run --exclude-group=<id> must skip that group. The
     * core method is private, so subclassing does not inherit it and it has to be repeated
     * here — without it the option is accepted by the command and silently ignored.
     *
     * @param string $groupId
     *
     * @return bool
     */
    private function isGroupInExcludeFilter($groupId): bool
    {
        $excludeGroup = $this->_request->getParam('exclude-group', []);

        return is_array($excludeGroup) && in_array($groupId, $excludeGroup);
    }

    /**
     * Is Group In Filter.
     *
     * @param string $groupId
     *
     * @return bool
     */
    private function isGroupInFilter($groupId): bool
    {
        return !($this->_request->getParam('group') !== null
                 && trim($this->_request->getParam('group'), "'") !== $groupId);
    }

    /**
     * Lock group
     *
     * It should be taken by standalone (child) process, not by the parent process.
     *
     * @param int $groupId
     * @param callable $callback
     *
     * @return void
     */
    private function lockGroup($groupId, callable $callback)
    {
        if (!$this->lockManager->lock(self::LOCK_PREFIX . $groupId, self::LOCK_TIMEOUT)) {
            $this->logger->warning(
                sprintf(
                    "Could not acquire lock for cron group: %s, skipping run",
                    $groupId
                )
            );

            return;
        }
        try {
            $callback($groupId);
        } finally {
            $this->lockManager->unlock(self::LOCK_PREFIX . $groupId);
        }
    }

    /**
     * Process error messages.
     *
     * @param Schedule $schedule
     * @param Throwable $exception
     *
     * @return void
     */
    private function processError(Schedule $schedule, Throwable $exception)
    {
        $schedule->setMessages($exception->getMessage());
        if ($schedule->getStatus() === Schedule::STATUS_ERROR) {
            $this->logger->critical($exception);
        }
        if ($schedule->getStatus() === Schedule::STATUS_MISSED
            && $this->state->getMode() === State::MODE_DEVELOPER
        ) {
            $this->logger->info($schedule->getMessages());
        }
    }

    /**
     * Process pending jobs.
     *
     * @param string $groupId
     * @param array $jobsRoot
     * @param int $currentTime
     *
     * @throws Throwable
     */
    private function processPendingJobs($groupId, $jobsRoot, $currentTime)
    {
        $procesedJobs = [];
        $pendingJobs = $this->getPendingSchedules($groupId);
        /** @var Schedule $schedule */
        foreach ($pendingJobs as $schedule) {
            if (isset($procesedJobs[$schedule->getJobCode()])) {
                // process only on job per run
                continue;
            }
            $jobConfig = isset($jobsRoot[$schedule->getJobCode()]) ? $jobsRoot[$schedule->getJobCode()] : null;
            if (!$jobConfig) {
                continue;
            }

            $scheduledTime = strtotime((string)$schedule->getScheduledAt());
            if ($scheduledTime > $currentTime) {
                continue;
            }

            try {
                if (!$schedule->tryLockJob()) {
                    // Another process already grabbed this schedule; nothing changed
                    // locally, so skip the trailing save() to avoid a redundant write.
                    continue;
                }
                // Expose the schedule so the shutdown handler can flag it if a
                // fatal error aborts the process during _runJob().
                $this->runningSchedule = $schedule;
                $this->scheduleHelper->setPid($schedule);
                $cpu_before = getrusage();
                $this->_runJob($scheduledTime, $currentTime, $jobConfig, $schedule, $groupId);
                $cpu_after = getrusage();
                $this->scheduleHelper->setCpuUsage($cpu_after, $cpu_before, $schedule);
                $this->scheduleHelper->setMemoryUsage($schedule);
            } catch (Throwable $e) {
                $this->processError($schedule, $e);
                $schedule->setErrorMessage($e->getMessage());
                $schedule->setErrorLine($e->getLine());
            } finally {
                // The run finished (success or handled failure); it is no longer
                // the process's in-flight job.
                $this->runningSchedule = null;
            }
            if ($schedule->getStatus() === Schedule::STATUS_SUCCESS) {
                $procesedJobs[$schedule->getJobCode()] = true;
            }
            $schedule->save();
        }
    }

    /**
     * Save a schedule of cron job.
     *
     * @param string $jobCode
     * @param string $cronExpression
     * @param int $timeInterval
     * @param array $exists
     *
     * @return void
     */
    protected function saveSchedule($jobCode, $cronExpression, $timeInterval, $exists)
    {
        $result = $this->jobHelper->isJobActive($jobCode);

        if ($result) {
            parent::saveSchedule($jobCode, $cronExpression, $timeInterval, $exists);
        }
    }

    /**
     * Starts profiling
     *
     * @return void
     */
    private function startProfiling()
    {
        $this->statProfiler->clear();
        $this->statProfiler->start('job', microtime(true), memory_get_usage(true), memory_get_usage());
    }

    /**
     * Stops profiling
     *
     * @return void
     */
    private function stopProfiling()
    {
        $this->statProfiler->stop('job', microtime(true), memory_get_usage(true), memory_get_usage());
    }
}
