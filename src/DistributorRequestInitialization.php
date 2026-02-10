<?php

namespace DigitalMarketingFramework\Distributor\Request;

use DigitalMarketingFramework\Core\Initialization;
use DigitalMarketingFramework\Core\Registry\RegistryDomain;
use DigitalMarketingFramework\Distributor\Core\DataDispatcher\DataDispatcherInterface;
use DigitalMarketingFramework\Distributor\Core\Route\OutboundRouteInterface;
use DigitalMarketingFramework\Distributor\Request\ConfigurationDocument\Migration\DynamicUrlMigration;
use DigitalMarketingFramework\Distributor\Request\DataDispatcher\RequestDataDispatcher;
use DigitalMarketingFramework\Distributor\Request\Route\RequestOutboundRoute;

class DistributorRequestInitialization extends Initialization
{
    protected const PLUGINS = [
        RegistryDomain::DISTRIBUTOR => [
            OutboundRouteInterface::class => [
                RequestOutboundRoute::class,
            ],
            DataDispatcherInterface::class => [
                RequestDataDispatcher::class,
            ],
        ],
    ];

    protected const SCHEMA_MIGRATIONS = [
        DynamicUrlMigration::class,
    ];

    public function __construct(string $packageAlias = '')
    {
        parent::__construct('distributor-request', '1.0.1', $packageAlias);
    }
}
