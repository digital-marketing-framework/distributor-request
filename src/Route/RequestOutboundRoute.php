<?php

namespace DigitalMarketingFramework\Distributor\Request\Route;

use DigitalMarketingFramework\Core\SchemaDocument\Schema\ContainerSchema;
use DigitalMarketingFramework\Core\SchemaDocument\Schema\MapSchema;
use DigitalMarketingFramework\Core\SchemaDocument\Schema\SchemaInterface;
use DigitalMarketingFramework\Core\SchemaDocument\Schema\StringSchema;
use DigitalMarketingFramework\Core\Context\ContextInterface;
use DigitalMarketingFramework\Core\Exception\DigitalMarketingFrameworkException;
use DigitalMarketingFramework\Distributor\Core\DataDispatcher\DataDispatcherInterface;
use DigitalMarketingFramework\Distributor\Core\Route\OutboundRoute;
use DigitalMarketingFramework\Distributor\Request\DataDispatcher\RequestDataDispatcherInterface;
use DigitalMarketingFramework\Distributor\Request\Exception\InvalidUrlException;

class RequestOutboundRoute extends OutboundRoute
{
    protected const KEY_URL = 'url';

    protected const DEFAULT_URL = '';

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

    public static function getIntegrationName(): string
    {
        return 'request';
    }

    public static function getIntegrationLabel(): ?string
    {
        return 'HTTP Request';
    }

    public static function getOutboundRouteListLabel(): ?string
    {
        return 'HTTP Request Routes';
    }

    /**
     * @return array<string,string>
     */
    protected function getSubmissionCookies(ContextInterface $context): array
    {
        $cookies = [];
        $cookieConfig = $this->getMapConfig(static::KEY_COOKIES);
        $cookieNamePatterns = [];
        foreach ($cookieConfig as $cookieName => $cookieValue) {
            if ($cookieValue === static::KEYWORD_PASSTHROUGH) {
                $cookieNamePatterns[] = $cookieName;
            }
        }

        foreach ($context->getCookies() as $cookieName => $cookieValue) {
            foreach ($cookieNamePatterns as $cookieNamePattern) {
                if (preg_match('/^' . $cookieNamePattern . '$/', $cookieName)) {
                    $cookies[$cookieName] = $cookieValue;
                }
            }
        }

        return $cookies;
    }

    /**
     * @param array<string,string> $submissionCookies
     *
     * @return array<string,?string>
     */
    protected function getCookies(array $submissionCookies): array
    {
        $cookies = [];
        $cookieConfig = $this->getMapConfig(static::KEY_COOKIES);
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
     * @return array<string>
     */
    protected function getPotentialInternalHeaderNames(string $headerName): array
    {
        // example: 'User-Agent' => ['User-Agent', 'HTTP_USER_AGENT', 'USER_AGENT']
        $name = preg_replace_callback('/-([A-Z])/', static function (array $matches) {
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
    protected function getSubmissionHeaders(ContextInterface $context): array
    {
        $headers = [];
        $headerConfig = $this->getMapConfig(static::KEY_HEADERS);
        foreach ($headerConfig as $headerName => $headerValuePattern) {
            if ($headerValuePattern === static::KEYWORD_PASSTHROUGH) {
                foreach ($this->getPotentialInternalHeaderNames($headerName) as $potentialHeaderName) {
                    $headerValue = $context->getRequestVariable($potentialHeaderName);
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
     * @param array<string,string> $submissionHeaders
     *
     * @return array<string,?string>
     */
    protected function getHeaders(array $submissionHeaders): array
    {
        $headers = [];
        $headerConfig = $this->getMapConfig(static::KEY_HEADERS);
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

    protected function getDispatcher(): DataDispatcherInterface
    {
        $url = $this->getConfig(static::KEY_URL);
        if (!$url) {
            throw new DigitalMarketingFrameworkException('No URL found for request dispatcher');
        }

        $cookies = $this->getCookies($this->submission->getContext()->getCookies());
        $headers = $this->getHeaders($this->submission->getContext()->getRequestVariables());

        try {
            /** @var RequestDataDispatcherInterface */
            $dispatcher = $this->registry->getDataDispatcher($this->getDispatcherKeyword());
            $dispatcher->setUrl($url);
            $dispatcher->addCookies($cookies);
            $dispatcher->addHeaders($headers);

            return $dispatcher;
        } catch (InvalidUrlException $e) {
            throw new DigitalMarketingFrameworkException($e->getMessage());
        }
    }

    public function addContext(ContextInterface $context): void
    {
        parent::addContext($context);

        $cookies = $this->getSubmissionCookies($context);
        foreach ($cookies as $name => $value) {
            $this->submission->getContext()->setCookie($name, $value);
        }

        $headers = $this->getSubmissionHeaders($context);
        foreach ($headers as $name => $value) {
            $this->submission->getContext()->setRequestVariable($name, $value);
        }
    }

    public static function getSchema(): SchemaInterface
    {
        /** @var ContainerSchema $schema */
        $schema = parent::getSchema();

        $urlSchema = new StringSchema();
        $urlSchema->getRenderingDefinition()->setLabel('URL');
        $urlSchema->setRequired();
        $urlProperty = $schema->addProperty(static::KEY_URL, $urlSchema);
        $urlProperty->setWeight(50);

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
