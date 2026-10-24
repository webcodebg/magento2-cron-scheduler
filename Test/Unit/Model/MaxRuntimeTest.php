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

use Magento\Framework\App\Config\ScopeConfigInterface;
use PHPUnit\Framework\TestCase;
use Webcode\CronScheduler\Model\MaxRuntime;

class MaxRuntimeTest extends TestCase
{
    /**
     * @var array<string, mixed>
     */
    private array $config = [];

    private function maxRuntime(): MaxRuntime
    {
        $scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturnCallback(fn (string $path) => $this->config[$path] ?? null);

        return new MaxRuntime($scopeConfig);
    }

    public function testWithNothingConfiguredItKeepsTheOldThreeHours(): void
    {
        $this->assertSame(180, $this->maxRuntime()->minutesFor('webcode_sync_synchronize_all'));
    }

    public function testTheGlobalSettingAppliesToEveryJob(): void
    {
        $this->config[MaxRuntime::XML_PATH_DEFAULT] = '60';

        $this->assertSame(60, $this->maxRuntime()->minutesFor('indexer_reindex_all_invalid'));
    }

    public function testAJobsOwnLimitWinsOnlyForThatJob(): void
    {
        $this->config[MaxRuntime::XML_PATH_DEFAULT] = '180';
        $this->config[MaxRuntime::jobPath('webcode_sync_synchronize_all')] = '240';

        $this->assertSame(240, $this->maxRuntime()->minutesFor('webcode_sync_synchronize_all'));
        $this->assertSame(180, $this->maxRuntime()->minutesFor('webcode_sync_synchronize'));
    }

    public function testZeroMeansNeverAndIsNotReadAsUnset(): void
    {
        $this->config[MaxRuntime::XML_PATH_DEFAULT] = '180';
        $this->config[MaxRuntime::jobPath('catalogsearch_fulltext')] = '0';

        $this->assertSame(0, $this->maxRuntime()->minutesFor('catalogsearch_fulltext'));
    }

    public function testAnEmptyOrInvalidJobValueFallsBackToTheGlobalOne(): void
    {
        $this->config[MaxRuntime::XML_PATH_DEFAULT] = '90';

        foreach (['', 'abc', '-5'] as $value) {
            $this->config[MaxRuntime::jobPath('some_job')] = $value;
            $this->assertSame(90, $this->maxRuntime()->minutesFor('some_job'), var_export($value, true));
        }
    }
}
