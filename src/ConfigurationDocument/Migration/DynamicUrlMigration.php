<?php

namespace DigitalMarketingFramework\Distributor\Request\ConfigurationDocument\Migration;

use DigitalMarketingFramework\Core\ConfigurationDocument\Migration\ConfigurationDocumentMigration;
use DigitalMarketingFramework\Core\ConfigurationDocument\Migration\MigrationContext;
use DigitalMarketingFramework\Core\DataProcessor\DataProcessor;
use DigitalMarketingFramework\Core\Model\Configuration\ConfigurationInterface;
use DigitalMarketingFramework\Core\SchemaDocument\Schema\SwitchSchema;
use DigitalMarketingFramework\Core\Utility\AbstractListUtility;
use DigitalMarketingFramework\Distributor\Core\Model\Configuration\DistributorConfigurationInterface;
use DigitalMarketingFramework\Distributor\Request\Route\RequestOutboundRoute;

class DynamicUrlMigration extends ConfigurationDocumentMigration
{
    public function getSourceVersion(): string
    {
        return '1.0.0';
    }

    public function getTargetVersion(): string
    {
        return '1.0.1';
    }

    protected function getIntegrationKeyword(): string
    {
        return 'request';
    }

    protected function getRouteKeyword(): string
    {
        return 'request';
    }

    public function migrate(array $delta, MigrationContext $context): array
    {
        $integrationKeyword = $this->getIntegrationKeyword();
        $routeKeyword = $this->getRouteKeyword();
        $completeConfiguration = $context->getEffectiveConfiguration($delta);

        $intKey = ConfigurationInterface::KEY_INTEGRATIONS;
        $routesKey = DistributorConfigurationInterface::KEY_OUTBOUND_ROUTES;
        $valueKey = AbstractListUtility::KEY_VALUE;
        $typeKey = SwitchSchema::KEY_TYPE;
        $configKey = SwitchSchema::KEY_CONFIG;
        $urlKey = RequestOutboundRoute::KEY_URL;

        foreach ($completeConfiguration[$intKey][$integrationKeyword][$routesKey] ?? [] as $uuid => $route) {
            $currentRouteKeyword = $route[$valueKey][$typeKey] ?? '';
            if ($currentRouteKeyword !== $routeKeyword) {
                continue;
            }

            if (!isset($delta[$intKey][$integrationKeyword][$routesKey][$uuid][$valueKey][$configKey][$routeKeyword][$urlKey])) {
                continue;
            }

            $url = $delta[$intKey][$integrationKeyword][$routesKey][$uuid][$valueKey][$configKey][$routeKeyword][$urlKey];
            if (is_string($url)) {
                $delta[$intKey][$integrationKeyword][$routesKey][$uuid][$valueKey][$configKey][$routeKeyword][$urlKey] = DataProcessor::valueSchemaDefaultValueConstant($url);
            }
        }

        return $delta;
    }
}
