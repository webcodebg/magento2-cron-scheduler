<?php

/*
 * @package      Webcode_CronScheduler
 *
 * @author       Kostadin Bashev (bashev@webcode.bg)
 * @copyright Copyright © 2020–2026 Webcode (https://webcode.bg/)
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License 3.0 (OSL-3.0)
 */

declare(strict_types=1);

namespace Webcode\CronScheduler\Ui\Component\Listing\Column\Status;

use Magento\Framework\Data\OptionSourceInterface;
use Webcode\CronScheduler\Model\ResourceModel\Schedule\Collection;
use Webcode\CronScheduler\Model\ResourceModel\Schedule\CollectionFactory;

class Options implements OptionSourceInterface
{
    /**
     * @var array|null
     */
    private ?array $options = null;

    /**
     * @var Collection
     */
    private readonly Collection $scheduleCollection;

    public function __construct(
        CollectionFactory $scheduleCollectionFactory
    ) {
        $this->scheduleCollection = $scheduleCollectionFactory->create();
    }

    /**
     * Get all options available
     * @return array
     */
    public function toOptionArray()
    {
        if ($this->options === null) {
            $this->options = [];
            $scheduleTaskStatuses = $this->scheduleCollection->getScheduleTaskStatuses();

            foreach ($scheduleTaskStatuses as $scheduleTaskStatus) {
                $status = $scheduleTaskStatus->getStatus();
                $this->options[] = [
                    "label" => __(strtoupper($status)),
                    "value" => $status
                ];
            }
        }

        return $this->options;
    }
}
