<?php

namespace DigitalMarketingFramework\Distributor\Request\DataDispatcher;

use DigitalMarketingFramework\Core\Exception\DigitalMarketingFrameworkException;
use DigitalMarketingFramework\Core\Model\Data\Value\ValueInterface;
use DigitalMarketingFramework\Distributor\Core\DataDispatcher\DataDispatcher;
use DigitalMarketingFramework\Distributor\Core\Model\Data\Value\DiscreteMultiValue;
use DigitalMarketingFramework\Distributor\Request\Exception\InvalidUrlException;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\SetCookie;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;

class RequestDataDispatcher extends DataDispatcher implements RequestDataDispatcherInterface
{
    protected $method = 'POST';

    protected $url = '';
    protected $headers = [];
    protected $cookies = [];

    protected function getDefaultHeaders(): array
    {
        return [
            'Content-Type' => 'application/x-www-form-urlencoded',
            'Accept' => '*/*',
        ];
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function setHeaders(array $headers): void
    {
        $this->headers = $headers;
    }

    public function addHeader(string $name, ?string $value): void
    {
        if ($value === null) {
            unset($this->headers[$name]);
        } else {
            $this->headers[$name] = $value;
        }
    }

    public function addHeaders(array $headers): void
    {
        foreach ($headers as $name => $value) {
            $this->addHeader($name, $value);
        }
    }

    public function removeHeader(string $name): void
    {
        $this->addHeader($name, null);
    }

    public function getCookies(): array
    {
        return $this->cookies;
    }

    public function setCookies(array $cookies): void
    {
        $this->cookies = $cookies;
    }

    public function addCookie(string $name, ?string $value): void
    {
        if ($value === null) {
            unset($this->cookies[$name]);
        } else {
            $this->cookies[$name] = $value;
        }
    }

    public function addCookies(array $cookies): void
    {
        foreach ($cookies as $name => $value) {
            $this->addCookie($name, $value);
        }
    }

    public function removeCookie(string $name): void
    {
        $this->addCookie($name, null);
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function setUrl(string $url): void
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (empty($host)) {
            throw new InvalidUrlException($url);
        }
        $this->url = $url;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function setMethod(string $method): void
    {
        $this->method = $method;
    }

    /**
     * url-encode data and parse fields of type DiscreteMultiValue
     * @param array<string,string|ValueInterface> $data
     * @return array<string>
     */
    protected function parameterize(array $data): array
    {
        $params = [];
        foreach ($data as $key => $value) {
            if ($value instanceof DiscreteMultiValue) {
                foreach ($value as $multiValue) {
                    $params[] = rawurlencode($key) . '=' . rawurlencode($multiValue);
                }
            } else {
                $params[] = rawurlencode($key) . '=' . rawurlencode($value);
            }
        }
        return $params;
    }

    /**
     * @param array<string,string|ValueInterface>
     */
    protected function buildBody(array $data): string
    {
        $params = $this->parameterize($data);
        return implode('&', $params);
    }

    protected function buildHeaders(array $data): array
    {
        $requestHeaders = $this->getDefaultHeaders();
        foreach ($this->headers as $key => $value) {
            if ($value === null) {
                unset($requestHeaders[$key]);
            } else {
                $requestHeaders[$key] = $value;
            }
        }
        return $requestHeaders;
    }

    protected function buildCookieJar(array $data): CookieJar
    {
        $requestCookies = [];
        if (!empty($this->cookies)) {
            $host = parse_url($this->url, PHP_URL_HOST);
            foreach ($this->cookies as $cKey => $cValue) {
                // Set up a cookie - name, value AND domain.
                $cookie = new SetCookie();
                $cookie->setName($cKey);
                $cookie->setValue(rawurlencode($cValue));
                $cookie->setDomain($host);
                $requestCookies[] = $cookie;
            }
        }
        return new CookieJar(false, $requestCookies);
    }

    protected function checkResponse(ResponseInterface $response): void
    {
        $statusCode = $response->getStatusCode();
        if ($statusCode < 200 || $statusCode >= 400) {
            throw new DigitalMarketingFrameworkException('Response status code indicates an error: ' . $statusCode);
        }
    }

    public function send(array $data): void
    {
        $requestOptions = [
            'body' => $this->buildBody($data),
            'cookies' => $this->buildCookieJar($data),
            'headers' => $this->buildHeaders($data),
        ];

        try {
            $client = new Client();
            $response = $client->request($this->method, $this->url, $requestOptions);
            $this->checkResponse($response);
        } catch (GuzzleException $e) {
            throw new DigitalMarketingFrameworkException($e->getMessage(), $e->getCode(), $e);
        }
    }
}
