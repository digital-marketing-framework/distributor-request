<?php

namespace DigitalMarketingFramework\Distributor\Request\DataDispatcher;

use DigitalMarketingFramework\Distributor\Core\DataDispatcher\DataDispatcherInterface;
use DigitalMarketingFramework\Distributor\Request\Exception\InvalidUrlException;

interface RequestDataDispatcherInterface extends DataDispatcherInterface
{
    public function getHeaders(): array;
    public function setHeaders(array $headers): void;
    public function addHeader(string $name, string $value): void;
    public function addHeaders(array $headers): void;
    public function removeHeader(string $name): void;

    public function getCookies(): array;
    public function setCookies(array $cookies): void;
    public function addCookie(string $name, string $value): void;
    public function addCookies(array $cookies): void;
    public function removeCookie(string $name): void;

    public function getUrl(): string;
    /**
     * @throws InvalidUrlException
     */
    public function setUrl(string $url): void;

    public function getMethod(): string;
    public function setMethod(string $method): void;
}
