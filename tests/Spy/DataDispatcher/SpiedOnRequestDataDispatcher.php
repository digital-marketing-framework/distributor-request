<?php

namespace DigitalMarketingFramework\Distributor\Request\Tests\Spy\DataDispatcher;

use DigitalMarketingFramework\Distributor\Core\Registry\RegistryInterface;
use DigitalMarketingFramework\Distributor\Request\DataDispatcher\RequestDataDispatcher;

class SpiedOnRequestDataDispatcher extends RequestDataDispatcher implements RequestDataDispatcherSpyInterface
{
    public function __construct(
        string $keyword,
        RegistryInterface $registry,
        public RequestDataDispatcherSpyInterface $spy,
    ) {
        parent::__construct($keyword, $registry);
    }

    public function send(array $data): void
    {
        $this->spy->send($data);
    }
    
    public function setUrl(string $url): void
    {
        $this->spy->setUrl($url);
    }

    public function addCookies(array $cookies): void
    {
        $this->spy->addCookies($cookies);
    }

    public function addHeaders(array $headers): void
    {
        $this->spy->addHeaders($headers);
    }
}
