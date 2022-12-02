<?php

namespace DigitalMarketingFramework\Distributor\Request;

use DigitalMarketingFramework\Core\PluginInitialization;
use DigitalMarketingFramework\Distributor\Core\DataDispatcher\DataDispatcherInterface;
use DigitalMarketingFramework\Distributor\Request\DataDispatcher\RequestDataDispatcher;

class DistributorPluginInitialization extends PluginInitialization
{
    protected const PLUGINS = [
        DataDispatcherInterface::class => [
            RequestDataDispatcher::class,
        ],
    ];
}
