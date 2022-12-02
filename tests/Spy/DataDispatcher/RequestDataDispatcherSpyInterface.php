<?php

namespace DigitalMarketingFramework\Distributor\Request\Tests\Spy\DataDispatcher;

use DigitalMarketingFramework\Distributor\Core\Tests\Spy\DataDispatcher\DataDispatcherSpyInterface;

interface RequestDataDispatcherSpyInterface extends DataDispatcherSpyInterface
{
    public function addHeaders(array $headers): void;
    public function addCookies(array $cookies): void;
    public function setUrl(string $url): void;
}
