<?php

/*
 * @package      Webcode_CronScheduler
 *
 * @author       Kostadin Bashev (bashev@webcode.bg)
 * @copyright Copyright © 2020–2026 Webcode (https://webcode.bg/)
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License 3.0 (OSL-3.0)
 */

declare(strict_types=1);

namespace Webcode\CronScheduler\Block\Adminhtml\DefaultBlocks;

use Exception;
use Magento\Framework\App\DeploymentConfig\Reader;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

class Setvalues extends Template
{
    /**
     * Class constructor.
     *
     * @param Context $context
     * @param Reader $configReader
     */
    public function __construct(
        Context $context,
        private readonly Reader $configReader
    ) {
        parent::__construct($context);
    }

    /**
     * Get Admin URL
     *
     * @return string
     * @throws Exception
     * @throws FileSystemException
     */
    public function getAdminBaseUrl(): string
    {
        $config = $this->configReader->load();
        $adminSuffix = $config['backend']['frontName'];

        return $this->getBaseUrl() . $adminSuffix . '/';
    }
}
