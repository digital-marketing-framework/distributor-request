<?php

namespace DigitalMarketingFramework\Distributor\Request;

use DigitalMarketingFramework\Core\PluginInitialization;
use DigitalMarketingFramework\Distributor\Core\Route\RouteInterface;
use DigitalMarketingFramework\Distributor\Request\Route\RequestRoute;

class DistributorRouteInitialization extends PluginInitialization
{
    protected const PLUGINS = [
        RouteInterface::class => [
            RequestRoute::class,
        ],
    ];
}
