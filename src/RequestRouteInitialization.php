<?php

namespace DigitalMarketingFramework\Distributor\Request;

use DigitalMarketingFramework\Core\Initialization;
use DigitalMarketingFramework\Distributor\Core\Route\RouteInterface;
use DigitalMarketingFramework\Distributor\Request\Route\RequestRoute;

class RequestRouteInitialization extends Initialization
{
    protected const PLUGINS = [
        RouteInterface::class => [
            RequestRoute::class,
        ],
    ];
}
