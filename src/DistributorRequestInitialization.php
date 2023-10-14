<?php

namespace DigitalMarketingFramework\Distributor\Request;

use DigitalMarketingFramework\Core\Initialization;
use DigitalMarketingFramework\Core\Registry\RegistryDomain;
use DigitalMarketingFramework\Distributor\Core\DataDispatcher\DataDispatcherInterface;
use DigitalMarketingFramework\Distributor\Core\Route\RouteInterface;
use DigitalMarketingFramework\Distributor\Request\DataDispatcher\RequestDataDispatcher;
use DigitalMarketingFramework\Distributor\Request\Route\RequestRoute;

class DistributorRequestInitialization extends Initialization
{
    protected const PLUGINS = [
        RegistryDomain::DISTRIBUTOR => [
            RouteInterface::class => [
                RequestRoute::class,
            ],
            DataDispatcherInterface::class => [
                RequestDataDispatcher::class,
            ],
        ],
    ];

    protected const SCHEMA_MIGRATIONS = [];

    public function __construct(string $packageAlias = '')
    {
        parent::__construct('distributor-request', '1.0.0', $packageAlias);
    }
}
