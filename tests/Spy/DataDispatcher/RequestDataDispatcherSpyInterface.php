<?php

namespace DigitalMarketingFramework\Distributor\Request\Tests\Spy\DataDispatcher;

use DigitalMarketingFramework\Distributor\Core\Tests\Spy\DataDispatcher\DataDispatcherSpyInterface;

interface RequestDataDispatcherSpyInterface extends DataDispatcherSpyInterface
{
    /**
     * @param array<string,?string> $headers
     */
    public function addHeaders(array $headers): void;

    /**
     * @param array<string,?string> $cookies
     */
    public function addCookies(array $cookies): void;

    public function setUrl(string $url): void;
}
