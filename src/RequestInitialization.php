<?php

namespace DigitalMarketingFramework\Distributor\Request;

use DigitalMarketingFramework\Core\Initialization;
use DigitalMarketingFramework\Distributor\Core\DataDispatcher\DataDispatcherInterface;
use DigitalMarketingFramework\Distributor\Request\DataDispatcher\RequestDataDispatcher;

class RequestInitialization extends Initialization
{
    protected const PLUGINS = [
        DataDispatcherInterface::class => [
            RequestDataDispatcher::class,
        ],
    ];
}
