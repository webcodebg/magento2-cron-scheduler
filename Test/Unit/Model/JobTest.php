<?php
/*
 * @package      Webcode_CronScheduler
 *
 * @author       Kostadin Bashev (bashev@webcode.bg)
 * @copyright Copyright © 2020–2026 Webcode (https://webcode.bg/)
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License 3.0 (OSL-3.0)
 */

declare(strict_types=1);

namespace Webcode\CronScheduler\Test\Unit\Model;

use Magento\Framework\App\Config\ConfigResource\ConfigInterface;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Webcode\CronScheduler\Model\Job;
use Webcode\CronScheduler\Model\MaxRuntime;

/**
 * Only a string of digits becomes a job's own kill limit; anything else removes it (see CLAUDE.md).
 */
class JobTest extends TestCase
{
    private const string JOB_CODE = 'webcode_sync_synchronize_all';

    /**
     * @var array<string, string>
     */
    private array $saved = [];

    /**
     * @var string[]
     */
    private array $deleted = [];

    private function save(string $maxRuntime): void
    {
        $config = $this->createStub(ConfigInterface::class);
        $config->method('saveConfig')->willReturnCallback(
            function (string $path, $value) use (&$config) {
                $this->saved[$path] = $value;

                return $config;
            }
        );
        $config->method('deleteConfig')->willReturnCallback(
            function (string $path) use (&$config) {
                $this->deleted[] = $path;

                return $config;
            }
        );

        $job = new Job($this->createStub(Context::class), $this->createStub(Registry::class), $config);
        $job->saveJob(
            [
                'group' => 'sync',
                'instance' => 'Webcode\Sync\Cron\SynchronizeAll',
                'method' => 'execute',
                'is_active' => '1',
                'max_runtime' => $maxRuntime,
            ],
            '10 4 * * *',
            self::JOB_CODE
        );
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function digits(): array
    {
        return [
            'minutes' => ['240', '240'],
            'zero means never' => ['0', '0'],
            'surrounding spaces' => [' 45 ', '45'],
        ];
    }

    #[DataProvider('digits')]
    public function testDigitsAreSavedAsTheJobsOwnLimit(string $input, string $stored): void
    {
        $this->save($input);

        $this->assertSame($stored, $this->saved[MaxRuntime::jobPath(self::JOB_CODE)] ?? null);
        $this->assertNotContains(MaxRuntime::jobPath(self::JOB_CODE), $this->deleted);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function noOverride(): array
    {
        return [
            'empty' => [''],
            'blank' => ['   '],
            'letters' => ['abc'],
            'negative' => ['-5'],
            'trailing garbage' => ['1.5x'],
            'decimal' => ['1.5'],
        ];
    }

    /**
     * Stored as 0 this would read as "never kill" and silently switch the killer off for the job.
     */
    #[DataProvider('noOverride')]
    public function testAnythingButDigitsRemovesTheOverrideInsteadOfSavingZero(string $input): void
    {
        $this->save($input);

        $this->assertArrayNotHasKey(MaxRuntime::jobPath(self::JOB_CODE), $this->saved);
        $this->assertContains(MaxRuntime::jobPath(self::JOB_CODE), $this->deleted);
    }
}
