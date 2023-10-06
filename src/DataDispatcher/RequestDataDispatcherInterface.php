<?php

namespace DigitalMarketingFramework\Distributor\Request\DataDispatcher;

use DigitalMarketingFramework\Distributor\Core\DataDispatcher\DataDispatcherInterface;
use DigitalMarketingFramework\Distributor\Request\Exception\InvalidUrlException;

interface RequestDataDispatcherInterface extends DataDispatcherInterface
{
    /**
     * @return array<string,?string>
     */
    public function getHeaders(): array;

    /**
     * @param array<string,?string> $headers
     */
    public function setHeaders(array $headers): void;

    public function addHeader(string $name, string $value): void;

    /**
     * @param array<string,?string> $headers
     */
    public function addHeaders(array $headers): void;

    public function removeHeader(string $name): void;

    /**
     * @return array<string,string>
     */
    public function getCookies(): array;

    /**
     * @param array<string,string> $cookies
     */
    public function setCookies(array $cookies): void;

    public function addCookie(string $name, string $value): void;

    /**
     * @param array<string,string> $cookies
     */
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
