<?php
/*
 * @package      Webcode_CronScheduler
 *
 * @author       Kostadin Bashev (bashev@webcode.bg)
 * @copyright Copyright © 2020–2026 Webcode (https://webcode.bg/)
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License 3.0 (OSL-3.0)
 */

declare(strict_types=1);

namespace Webcode\CronScheduler\Test\Unit\Cron;

use ArrayIterator;
use Magento\Cron\Model\Schedule;
use Magento\Framework\Stdlib\DateTime\DateTime;
use PHPUnit\Framework\TestCase;
use Webcode\CronScheduler\Cron\LongJobChecker;
use Webcode\CronScheduler\Model\MaxRuntime;
use Webcode\CronScheduler\Model\ProcessControl;
use Webcode\CronScheduler\Model\ResourceModel\Schedule\Collection;
use Webcode\CronScheduler\Model\ResourceModel\Schedule\CollectionFactory;

/**
 * Each running job is measured against its own limit (see CLAUDE.md).
 */
class LongJobCheckerTest extends TestCase
{
    /**
     * The moment the full sync job was killed on 15.09.2026, in UTC.
     */
    private const string NOW = '2026-09-15 04:15:03';

    /**
     * @var int[]
     */
    private array $killed = [];

    /**
     * @var array<string, int>
     */
    private array $limits = [];

    /**
     * @var array<int, bool>
     */
    private array $alive = [];

    /**
     * @param Schedule[] $jobs
     */
    private function check(array $jobs): void
    {
        $collection = $this->createStub(Collection::class);
        $collection->method('addFieldToFilter')->willReturnSelf();
        $collection->method('addFieldToSelect')->willReturnSelf();
        $collection->method('getIterator')->willReturn(new ArrayIterator($jobs));

        $factory = $this->createStub(CollectionFactory::class);
        $factory->method('create')->willReturn($collection);

        $dateTime = $this->createStub(DateTime::class);
        $dateTime->method('gmtTimestamp')->willReturn(strtotime(self::NOW . ' UTC'));
        $dateTime->method('gmtDate')->willReturn(self::NOW);

        $maxRuntime = $this->createStub(MaxRuntime::class);
        $maxRuntime->method('minutesFor')->willReturnCallback(fn (string $code): int => $this->limits[$code] ?? 180);

        $process = $this->createStub(ProcessControl::class);
        $process->method('isAlive')->willReturnCallback(fn (int $pid): bool => $this->alive[$pid] ?? true);
        $process->method('kill')->willReturnCallback(function (int $pid): void {
            $this->killed[] = $pid;
        });

        (new LongJobChecker($dateTime, $factory, $maxRuntime, $process))->execute();
    }

    private function job(string $code, string $executedAt, ?string $pid = '4242'): Schedule
    {
        $job = new class extends Schedule {
            public function __construct()
            {
            }

            public function save()
            {
                return $this;
            }
        };
        $job->setData(['job_code' => $code, 'executed_at' => $executedAt, 'pid' => $pid]);

        return $job;
    }

    public function testAJobPastTheDefaultThreeHoursIsKilled(): void
    {
        $job = $this->job('webcode_sync_synchronize_all', '2026-09-15 01:10:04');

        $this->check([$job]);

        $this->assertSame([4242], $this->killed);
        $this->assertSame(LongJobChecker::STATUS_KILLED, $job->getData('status'));
        $this->assertSame('It is killed as running for longer period.', (string)$job->getData('messages'));
        $this->assertSame(self::NOW, $job->getData('finished_at'));
    }

    public function testAJobWithItsOwnLongerLimitKeepsRunning(): void
    {
        $this->limits['webcode_sync_synchronize_all'] = 240;
        $job = $this->job('webcode_sync_synchronize_all', '2026-09-15 01:10:04');

        $this->check([$job]);

        $this->assertSame([], $this->killed);
        $this->assertNull($job->getData('status'));
    }

    public function testAJobWithItsOwnShorterLimitIsKilledSooner(): void
    {
        $this->limits['webcode_sync_synchronize'] = 30;

        $this->check([$this->job('webcode_sync_synchronize', '2026-09-15 03:30:00')]);

        $this->assertSame([4242], $this->killed);
    }

    public function testZeroMeansTheJobIsNeverKilled(): void
    {
        $this->limits['catalogsearch_fulltext'] = 0;

        $this->check([$this->job('catalogsearch_fulltext', '2026-09-10 00:00:00')]);

        $this->assertSame([], $this->killed);
    }

    public function testAJobUnderItsLimitIsLeftAlone(): void
    {
        $job = $this->job('webcode_sync_synchronize', '2026-09-15 04:00:00');

        $this->check([$job]);

        $this->assertSame([], $this->killed);
        $this->assertNull($job->getData('status'));
    }

    public function testADeadProcessIsMarkedAsErrorInsteadOfSignalled(): void
    {
        $this->alive[4242] = false;
        $job = $this->job('webcode_sync_synchronize_all', '2026-09-15 01:10:04');

        $this->check([$job]);

        $this->assertSame([], $this->killed);
        $this->assertSame(Schedule::STATUS_ERROR, $job->getData('status'));
    }

    /**
     * posix_kill(0) would signal the checker's own process group, i.e. cron:run itself.
     */
    public function testARowWithoutAPidIsNeverSignalled(): void
    {
        $this->check([
            $this->job('webcode_sync_synchronize_all', '2026-09-15 01:10:04', null),
            $this->job('webcode_sync_synchronize_all', '2026-09-15 01:10:04', ''),
        ]);

        $this->assertSame([], $this->killed);
    }
}
