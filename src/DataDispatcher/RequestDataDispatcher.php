<?php

namespace DigitalMarketingFramework\Distributor\Request\DataDispatcher;

use DigitalMarketingFramework\Core\Exception\DigitalMarketingFrameworkException;
use DigitalMarketingFramework\Core\Model\Data\Value\MultiValueInterface;
use DigitalMarketingFramework\Core\Model\Data\Value\ValueInterface;
use DigitalMarketingFramework\Distributor\Core\DataDispatcher\DataDispatcher;
use DigitalMarketingFramework\Distributor\Core\Model\Data\Value\DiscreteMultiValue;
use DigitalMarketingFramework\Distributor\Core\Registry\RegistryInterface;
use DigitalMarketingFramework\Distributor\Request\Exception\InvalidUrlException;
use DigitalMarketingFramework\Distributor\Request\ResponseValidation\ResponseValidationInterface;
use DigitalMarketingFramework\Distributor\Request\ResponseValidation\StatusCodeResponseValidation;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\SetCookie;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;

class RequestDataDispatcher extends DataDispatcher implements RequestDataDispatcherInterface
{
    public const MULTI_VALUE_FORMAT_FLAT = 'flat';

    public const MULTI_VALUE_FORMAT_NESTED = 'nested';

    protected string $method = 'POST';

    protected string $url = '';

    protected string $multiValueFormat = self::MULTI_VALUE_FORMAT_FLAT;

    /** @var array<string,?string> */
    protected array $headers = [];

    /** @var array<string,string> */
    protected array $cookies = [];

    /** @var array<ResponseValidationInterface> */
    protected array $responseValidations = [];

    public function __construct(string $keyword, RegistryInterface $registry)
    {
        parent::__construct($keyword, $registry);
        $this->responseValidations = [new StatusCodeResponseValidation()];
    }

    public function addResponseValidation(ResponseValidationInterface $responseValidation): void
    {
        $this->responseValidations[] = $responseValidation;
    }

    /**
     * @return array<ResponseValidationInterface>
     */
    public function getResponseValidations(): array
    {
        return $this->responseValidations;
    }

    /**
     * @param array<ResponseValidationInterface> $responseValidations
     */
    public function setResponseValidations(array $responseValidations): void
    {
        $this->responseValidations = $responseValidations;
    }

    public function clearResponseValidations(): void
    {
        $this->responseValidations = [];
    }

    /**
     * @return array<string,string>
     */
    protected function getDefaultHeaders(): array
    {
        $headers = [
            'Accept' => '*/*',
        ];

        if (!$this->isGetRequest()) {
            $headers['Content-Type'] = 'application/x-www-form-urlencoded';
        }

        return $headers;
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
        $this->headers[$name] = $value;
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
        if (!is_string($host) || $host === '') {
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

    public function getMultiValueFormat(): string
    {
        return $this->multiValueFormat;
    }

    public function setMultiValueFormat(string $multiValueFormat): void
    {
        $this->multiValueFormat = $multiValueFormat;
    }

    protected function isGetRequest(): bool
    {
        return strtoupper($this->method) === 'GET';
    }

    /**
     * Flatten a value into URL-encoded key=value parameter strings (flat mode).
     * DiscreteMultiValue repeats the key, everything else is cast to string.
     *
     * @param array<string,string|ValueInterface> $data
     *
     * @return array<string>
     */
    protected function parameterizeFlat(array $data): array
    {
        $params = [];
        foreach ($data as $key => $value) {
            if ($value instanceof DiscreteMultiValue) {
                foreach ($value as $multiValue) {
                    $params[] = rawurlencode($key) . '=' . rawurlencode((string)$multiValue);
                }
            } else {
                $params[] = rawurlencode($key) . '=' . rawurlencode((string)$value);
            }
        }

        return $params;
    }

    /**
     * Recursively flatten a single value into URL-encoded parameter strings (nested mode).
     * DiscreteMultiValue repeats the key at the current nesting level.
     * MultiValue uses bracket notation for sub-keys.
     * Scalar values are encoded directly.
     *
     * @param array<string> $params collected parameter strings (modified by reference)
     */
    protected function flattenToParams(string $key, string|ValueInterface $value, array &$params): void
    {
        if ($value instanceof DiscreteMultiValue) {
            foreach ($value as $item) {
                $this->flattenToParams($key, $item, $params);
            }
        } elseif ($value instanceof MultiValueInterface) {
            foreach ($value as $subKey => $item) {
                $this->flattenToParams($key . '[' . $subKey . ']', $item, $params);
            }
        } else {
            $params[] = rawurlencode($key) . '=' . rawurlencode((string)$value);
        }
    }

    /**
     * Flatten data into URL-encoded key=value parameter strings (nested mode).
     * MultiValues produce bracket-notation keys, DiscreteMultiValues repeat keys.
     *
     * @param array<string,string|ValueInterface> $data
     *
     * @return array<string>
     */
    protected function parameterizeNested(array $data): array
    {
        $params = [];
        foreach ($data as $key => $value) {
            $this->flattenToParams($key, $value, $params);
        }

        return $params;
    }

    /**
     * URL-encode data into key=value parameter strings.
     *
     * @param array<string,string|ValueInterface> $data
     *
     * @return array<string>
     */
    protected function parameterize(array $data): array
    {
        if ($this->multiValueFormat === self::MULTI_VALUE_FORMAT_NESTED) {
            return $this->parameterizeNested($data);
        }

        return $this->parameterizeFlat($data);
    }

    /**
     * @param array<string,string|ValueInterface> $data
     */
    protected function buildBody(array $data): string
    {
        $params = $this->parameterize($data);

        return implode('&', $params);
    }

    /**
     * Build the full URL with query string for GET requests.
     *
     * @param array<string,string|ValueInterface> $data
     */
    protected function buildGetUrl(array $data): string
    {
        $queryString = $this->buildBody($data);
        if ($queryString === '') {
            return $this->url;
        }

        $separator = str_contains($this->url, '?') ? '&' : '?';

        return $this->url . $separator . $queryString;
    }

    /**
     * @param array<string,string|ValueInterface> $data
     *
     * @return array<string,string>
     */
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

    /**
     * @param array<string,string|ValueInterface> $data
     */
    protected function buildCookieJar(array $data): CookieJar
    {
        $requestCookies = [];
        if ($this->cookies !== []) {
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
        foreach ($this->responseValidations as $responseValidation) {
            $responseValidation->validate($response);
        }
    }

    public function send(array $data): void
    {
        $requestOptions = [
            'cookies' => $this->buildCookieJar($data),
            'headers' => $this->buildHeaders($data),
        ];

        if ($this->isGetRequest()) {
            $url = $this->buildGetUrl($data);
        } else {
            $url = $this->url;
            $requestOptions['body'] = $this->buildBody($data);
        }

        try {
            $client = new Client();
            $response = $client->request($this->method, $url, $requestOptions);
            $this->checkResponse($response);
        } catch (GuzzleException $e) {
            throw new DigitalMarketingFrameworkException('Status code: ' . $e->getCode(), $e->getCode(), $e);
        }
    }

    protected function transformDataForPreview(array $data): array
    {
        return array_map(static function (ValueInterface|string $value) {
            if ($value instanceof DiscreteMultiValue) {
                return array_map(static fn (ValueInterface|string $multiValue): string => (string)$multiValue, $value->toArray());
            }

            return (string)$value;
        }, $data);
    }

    public function preview(array $data): array
    {
        $previewData = parent::preview($data);

        $previewData['config']['URL'] = $this->url;

        $previewData['config']['Method'] = $this->method;

        $previewData['config']['MultiValueFormat'] = $this->multiValueFormat;

        $previewData['headers'] = $this->buildHeaders($data);

        $previewData['cookies'] = [];
        foreach ($this->buildCookieJar($data)->toArray() as $cookie) {
            $previewData['cookies'][$cookie['Name']] = $cookie['Value'];
        }

        if ($this->isGetRequest()) {
            $previewData['query'] = $this->buildGetUrl($data);
        } else {
            $previewData['body'] = $this->buildBody($data);
        }

        return $previewData;
    }
}
