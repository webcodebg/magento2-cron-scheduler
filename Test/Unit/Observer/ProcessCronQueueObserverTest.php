<?php
/*
 * @package      Webcode_CronScheduler
 *
 * @author       Kostadin Bashev (bashev@webcode.bg)
 * @copyright Copyright © 2020–2026 Webcode (https://webcode.bg/)
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License 3.0 (OSL-3.0)
 */

declare(strict_types=1);

namespace Webcode\CronScheduler\Test\Unit\Observer;

use Magento\Framework\App\Console\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Webcode\CronScheduler\Observer\ProcessCronQueueObserver;

/**
 * The group filters decide which groups a cron:run touches, so they are checked on the
 * override itself: core's isGroupInExcludeFilter() is private and is not inherited.
 */
class ProcessCronQueueObserverTest extends TestCase
{
    /**
     * @return array<string, array{0: mixed, 1: string, 2: bool}>
     */
    public static function excludeFilterCases(): array
    {
        return [
            'the excluded group is skipped' => [['index'], 'index', true],
            'another group still runs' => [['index'], 'default', false],
            'several excluded groups' => [['index', 'consumers'], 'consumers', true],
            'empty exclude list runs everything' => [[], 'default', false],
            'option not passed at all' => [null, 'default', false],
            'non-array value is ignored' => ['index', 'index', false],
        ];
    }

    #[DataProvider('excludeFilterCases')]
    public function testExcludeGroupOptionIsHonoured(mixed $excluded, string $groupId, bool $skipped): void
    {
        $request = $this->createStub(Request::class);
        $request->method('getParam')->willReturnCallback(
            fn (string $key, mixed $default = null) => $key === 'exclude-group' ? ($excluded ?? $default) : $default
        );

        $observer = (new ReflectionClass(ProcessCronQueueObserver::class))->newInstanceWithoutConstructor();
        $requestProperty = new \ReflectionProperty(\Magento\Cron\Observer\ProcessCronQueueObserver::class, '_request');
        $requestProperty->setValue($observer, $request);

        $method = new \ReflectionMethod(ProcessCronQueueObserver::class, 'isGroupInExcludeFilter');

        $this->assertSame($skipped, $method->invoke($observer, $groupId));
    }
}
