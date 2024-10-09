<?php

namespace DigitalMarketingFramework\Distributor\Request\Route;

use DigitalMarketingFramework\Core\Context\WriteableContextInterface;
use DigitalMarketingFramework\Core\Exception\DigitalMarketingFrameworkException;
use DigitalMarketingFramework\Core\Integration\IntegrationInfo;
use DigitalMarketingFramework\Core\SchemaDocument\RenderingDefinition\RenderingDefinitionInterface;
use DigitalMarketingFramework\Core\SchemaDocument\Schema\ContainerSchema;
use DigitalMarketingFramework\Core\SchemaDocument\Schema\MapSchema;
use DigitalMarketingFramework\Core\SchemaDocument\Schema\SchemaInterface;
use DigitalMarketingFramework\Core\SchemaDocument\Schema\StringSchema;
use DigitalMarketingFramework\Distributor\Core\DataDispatcher\DataDispatcherInterface;
use DigitalMarketingFramework\Distributor\Core\Route\OutboundRoute;
use DigitalMarketingFramework\Distributor\Request\DataDispatcher\RequestDataDispatcherInterface;
use DigitalMarketingFramework\Distributor\Request\Exception\InvalidUrlException;

class RequestOutboundRoute extends OutboundRoute
{
    protected const KEY_URL = 'url';

    protected const DEFAULT_URL = '';

    protected const KEY_METHOD = 'method';

    protected const DEFAULT_METHOD = 'POST';

    protected const KEYWORD_PASSTHROUGH = '{value}';

    protected const KEYWORD_UNSET = '{null}';

    /**
     * example cookie configurations
     *
     * cookies:
     *     cookieName1: constantCookieValue1
     *     cookieName2: {value}
     *     cookieNameRegexpPattern3: {value}
     *     cookieName4: {null}
     */
    protected const KEY_COOKIES = 'cookies';

    protected const DEFAULT_COOKIES = [];

    /**
     * example header configurations
     *
     * headers:
     *     User-Agent: {value}
     *     Accept: application/json
     *     Content-Type: {null}
     */
    protected const KEY_HEADERS = 'headers';

    protected const DEFAULT_HEADERS = [];

    public static function getDefaultIntegrationInfo(): IntegrationInfo
    {
        return new IntegrationInfo('request', 'HTTP Request', outboundRouteListLabel: 'HTTP Request Routes');
    }

    public static function getLabel(): ?string
    {
        return 'HTTP Request';
    }

    /**
     * @return array<string,string>
     */
    protected function getCookieConfig(): array
    {
        return $this->getMapConfig(static::KEY_COOKIES);
    }

    /**
     * @return array<string,string>
     */
    protected function getSubmissionCookies(): array
    {
        $cookies = [];
        $cookieConfig = $this->getCookieConfig();
        $cookieNamePatterns = [];
        foreach ($cookieConfig as $cookieName => $cookieValue) {
            if ($cookieValue === static::KEYWORD_PASSTHROUGH) {
                $cookieNamePatterns[] = $cookieName;
            }
        }

        foreach ($this->context->getCookies() as $cookieName => $cookieValue) {
            foreach ($cookieNamePatterns as $cookieNamePattern) {
                if (preg_match('/^' . $cookieNamePattern . '$/', $cookieName)) {
                    $cookies[$cookieName] = $cookieValue;
                }
            }
        }

        return $cookies;
    }

    /**
     * @return array<string,?string>
     */
    protected function getCookies(): array
    {
        $submissionCookies = $this->context->getCookies();
        $cookies = [];
        $cookieConfig = $this->getCookieConfig();
        foreach ($cookieConfig as $cookieName => $cookieValue) {
            switch ($cookieValue) {
                case static::KEYWORD_PASSTHROUGH:
                    $cookieNamePattern = '/^' . $cookieName . '$/';
                    foreach ($submissionCookies as $submissionCookieName => $submissionCookieValue) {
                        if (preg_match($cookieNamePattern, $submissionCookieName)) {
                            $cookies[$submissionCookieName] = $submissionCookieValue;
                        }
                    }

                    break;
                case static::KEYWORD_UNSET:
                    $cookies[$cookieName] = null;
                    break;
                default:
                    $cookies[$cookieName] = $cookieValue;
                    break;
            }
        }

        return $cookies;
    }

    /**
     * @return array<string,string>
     */
    protected function getHeaderConfig(): array
    {
        return $this->getMapConfig(static::KEY_HEADERS);
    }

    /**
     * @return array<string>
     */
    protected function getPotentialInternalHeaderNames(string $headerName): array
    {
        // example: 'User-Agent' => ['User-Agent', 'HTTP_USER_AGENT', 'USER_AGENT']
        $name = preg_replace_callback('/-([A-Z])/', static function (array $matches): string {
            return '_' . $matches[1];
        }, $headerName);
        $name = strtoupper($name);

        return [
            $headerName,
            'HTTP_' . $name,
            $name,
        ];
    }

    /**
     * Headers to be passed from the submission request (during context processing)
     *
     * @return array<string,string>
     */
    protected function getSubmissionHeaders(): array
    {
        $headers = [];
        $headerConfig = $this->getHeaderConfig();
        foreach ($headerConfig as $headerName => $headerValuePattern) {
            if ($headerValuePattern === static::KEYWORD_PASSTHROUGH) {
                foreach ($this->getPotentialInternalHeaderNames($headerName) as $potentialHeaderName) {
                    $headerValue = $this->context->getRequestVariable($potentialHeaderName);
                    if ($headerValue) {
                        $headers[$potentialHeaderName] = $headerValue;
                        break;
                    }
                }
            }
        }

        return $headers;
    }

    /**
     * Headers to be sent with the upcoming http request
     *
     * @return array<string,?string>
     */
    protected function getHeaders(): array
    {
        $submissionHeaders = $this->context->getRequestVariables();
        $headers = [];
        $headerConfig = $this->getHeaderConfig();
        foreach ($headerConfig as $headerName => $headerValue) {
            switch ($headerValue) {
                case static::KEYWORD_PASSTHROUGH:
                    $headerValue = null;
                    foreach ($this->getPotentialInternalHeaderNames($headerName) as $potentialHeaderName) {
                        if (isset($submissionHeaders[$potentialHeaderName])) {
                            $headerValue = $submissionHeaders[$potentialHeaderName];
                            break;
                        }
                    }

                    if ($headerValue !== null) {
                        $headers[$headerName] = $headerValue;
                    }

                    break;
                case static::KEYWORD_UNSET:
                    $headers[$headerName] = null;
                    break;
                default:
                    $headers[$headerName] = $headerValue;
                    break;
            }
        }

        return $headers;
    }

    protected function getDispatcherKeyword(): string
    {
        return 'request';
    }

    protected function getMethod(): string
    {
        return $this->getConfig(static::KEY_METHOD);
    }

    protected function getDispatcher(): DataDispatcherInterface
    {
        $url = $this->getConfig(static::KEY_URL);
        if (!$url) {
            throw new DigitalMarketingFrameworkException('No URL found for request dispatcher');
        }

        $cookies = $this->getCookies();
        $headers = $this->getHeaders();
        $method = $this->getMethod();

        try {
            /** @var RequestDataDispatcherInterface */
            $dispatcher = $this->registry->getDataDispatcher($this->getDispatcherKeyword());
            $dispatcher->setUrl($url);
            $dispatcher->addCookies($cookies);
            $dispatcher->addHeaders($headers);
            $dispatcher->setMethod($method);

            return $dispatcher;
        } catch (InvalidUrlException $e) {
            throw new DigitalMarketingFrameworkException($e->getMessage());
        }
    }

    public function addContext(WriteableContextInterface $context): void
    {
        parent::addContext($context);

        $cookies = $this->getSubmissionCookies();
        foreach ($cookies as $name => $value) {
            $context->setCookie($name, $value);
        }

        $headers = $this->getSubmissionHeaders();
        foreach ($headers as $name => $value) {
            $context->setRequestVariable($name, $value);
        }
    }

    public static function getSchema(): SchemaInterface
    {
        /** @var ContainerSchema $schema */
        $schema = parent::getSchema();

        $urlSchema = new StringSchema(static::DEFAULT_URL);
        $urlSchema->getRenderingDefinition()->setLabel('URL');
        $urlSchema->setRequired();
        $urlProperty = $schema->addProperty(static::KEY_URL, $urlSchema);
        $urlProperty->setWeight(50);

        $methodSchema = new StringSchema(static::DEFAULT_METHOD);
        $methodSchema->getAllowedValues()->addValue('POST');
        $methodSchema->getAllowedValues()->addValue('GET');
        $methodSchema->getAllowedValues()->addValue('PUT');
        $methodSchema->getAllowedValues()->addValue('DELETE');
        $methodSchema->getRenderingDefinition()->setFormat(RenderingDefinitionInterface::FORMAT_SELECT);
        $methodProperty = $schema->addProperty(static::KEY_METHOD, $methodSchema);
        $methodProperty->setWeight(50);

        $cookieValueSchema = new StringSchema(static::KEYWORD_PASSTHROUGH);
        $cookieValueSchema->getSuggestedValues()->addValue(static::KEYWORD_PASSTHROUGH);
        $cookieValueSchema->getSuggestedValues()->addValue(static::KEYWORD_UNSET);
        $cookieValueSchema->getRenderingDefinition()->setLabel('Cookie Value');
        $cookieNameSchema = new StringSchema('cookieName');
        $cookieNameSchema->getRenderingDefinition()->setLabel('Cookie Name/Pattern');
        $cookiesSchema = new MapSchema(
            $cookieValueSchema,
            $cookieNameSchema,
            static::DEFAULT_COOKIES
        );
        $cookiesSchema->getRenderingDefinition()->setNavigationItem(false);
        $schema->addProperty(static::KEY_COOKIES, $cookiesSchema);

        $headerValueSchema = new StringSchema(static::KEYWORD_PASSTHROUGH);
        $headerValueSchema->getSuggestedValues()->addValue(static::KEYWORD_PASSTHROUGH);
        $headerValueSchema->getSuggestedValues()->addValue(static::KEYWORD_UNSET);
        $headerValueSchema->getRenderingDefinition()->setLabel('Header Value');
        $headerNameSchema = new StringSchema('headerName');
        $headerNameSchema->getRenderingDefinition()->setLabel('Header Name/Pattern');
        $headersSchema = new MapSchema(
            $headerValueSchema,
            $headerNameSchema,
            static::DEFAULT_HEADERS
        );
        $headersSchema->getRenderingDefinition()->setNavigationItem(false);
        $schema->addProperty(static::KEY_HEADERS, $headersSchema);

        return $schema;
    }
}
