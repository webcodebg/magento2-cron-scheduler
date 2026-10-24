<?php
/*
 * @package      Webcode_CronScheduler
 *
 * @author       Kostadin Bashev (bashev@webcode.bg)
 * @copyright Copyright © 2020–2026 Webcode (https://webcode.bg/)
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License 3.0 (OSL-3.0)
 */

declare(strict_types=1);

namespace Webcode\CronScheduler\Ui\DataProvider\Group;

use Magento\Cron\Model\ConfigInterface;
use Magento\Framework\Data\OptionSourceInterface;

class Options implements OptionSourceInterface
{
    /**
     * @var array|null
     */
    private ?array $options = null;

    public function __construct(
        private readonly ConfigInterface $cronConfig
    ) {
    }

    /**
     * Get all options available
     * @return array
     */
    public function toOptionArray()
    {
        if ($this->options === null) {
            $configJobs = $this->cronConfig->getJobs();
            foreach (array_keys($configJobs) as $group) {
                $this->options[] = [
                    "label" => $group, "value" => $group
                ];
            }
        }
        return $this->options;
    }
}
