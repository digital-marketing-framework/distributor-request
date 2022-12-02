<?php

namespace DigitalMarketingFramework\Distributor\Request\Route;

use DigitalMarketingFramework\Core\Context\ContextInterface;
use DigitalMarketingFramework\Core\Service\DataProcessorInterface;
use DigitalMarketingFramework\Distributor\Core\DataDispatcher\DataDispatcherInterface;
use DigitalMarketingFramework\Distributor\Core\Route\Route;
use DigitalMarketingFramework\Distributor\Request\DataDispatcher\RequestDataDispatcherInterface;
use DigitalMarketingFramework\Distributor\Request\Exception\InvalidUrlException;

class RequestRoute extends Route
{
    protected const KEY_URL = 'url';
    protected const DEFAULT_URL = '';

    protected const KEYWORD_PASSTHROUGH = '__PASSTHROUGH';
    protected const KEYWORD_UNSET = '__UNSET';

    /*
     * example cookie configurations
     * 
     * # just pass through the cookies that match one of the listed cookie name patterns
     * cookies:
     *     - cookieName1
     *     - cookieName2
     *     - cookieNameRegexpPattern3
     * 
     * # advanced cookie configuration
     * cookies:
     *     cookieName1: constantCookieValue1
     *     cookieName2: __PASSTHROUGH
     *     cookieNameRegexpPattern3: __PASSTHROUGH
     *     cookieName4: __UNSET
     */
    protected const KEY_COOKIES = 'cookies';
    protected const DEFAULT_COOKIES = [];

    /*
     * example header configurations
     * 
     * # just pass through the listed headers
     * headers:
     *     - User-Agent
     *     - Accept
     * 
     * # advanced header configuration
     * headers:
     *     User-Agent: __PASSTHROUGH
     *     Accept: application/json
     *     Content-Type: __UNSET
     */
    protected const KEY_HEADERS = 'headers';
    protected const DEFAULT_HEADERS = [];

    protected const KEY_FIELDS = DataProcessorInterface::KEY_FIELDS;
    protected const DEFAULT_FIELDS = DataProcessorInterface::DEFAULT_FIELDS;
    
    protected function getUrl(): string
    {
        $url = $this->getConfig(static::KEY_URL);
        if ($url) {
            $url = $this->resolveContent($url);
        }
        return $url ? $url : '';
    }

    protected function getSubmissionCookies(ContextInterface $context): array
    {
        $cookies = [];
        $cookieConfig = $this->getConfig(static::KEY_COOKIES);
        $cookieNamePatterns = [];
        foreach ($cookieConfig as $cookieName => $cookieValue) {
            if (is_numeric($cookieName)) {
                $cookieName = $cookieValue;
                $cookieValue = static::KEYWORD_PASSTHROUGH;
            }
            $cookieValue = $this->resolveContent($cookieValue);
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

    protected function getCookies(array $submissionCookies): array
    {
        $cookies = [];
        $cookieConfig = $this->getConfig(static::KEY_COOKIES);
        foreach ($cookieConfig as $cookieName => $cookieValue) {
            if (is_numeric($cookieName)) {
                $cookieName = $cookieValue;
                $cookieValue = static::KEYWORD_PASSTHROUGH;
            }
            $cookieValue = $this->resolveContent($cookieValue);
            if ($cookieValue === null) {
                continue;
            }
            switch ($cookieValue) {
                case static::KEYWORD_PASSTHROUGH:
                    $cookieNamePattern = '/^' .$cookieName . '$/';
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

    protected function getPotentialInternalHeaderNames(string $headerName): array
    {
        // example: 'User-Agent' => ['User-Agent', 'HTTP_USER_AGENT', 'USER_AGENT']
        $name = preg_replace_callback('/-([A-Z])/', function(array $matches) {
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
     */
    protected function getSubmissionHeaders(ContextInterface $context): array
    {
        $headers = [];
        $headerConfig = $this->getConfig(static::KEY_HEADERS);
        foreach ($headerConfig as $headerName => $headerValue) {
            if (is_numeric($headerName)) {
                $headerName = $headerValue;
                $headerValue = static::KEYWORD_PASSTHROUGH;
            }
            $headerValue = $this->resolveContent($headerValue);
            if ($headerValue === static::KEYWORD_PASSTHROUGH) {
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
     * @param array $submissionHeaders
     * @return array
     */
    protected function getHeaders(array $submissionHeaders): array
    {
        $headers = [];
        $headerConfig = $this->getConfig(static::KEY_HEADERS);
        foreach ($headerConfig as $headerName => $headerValue) {
            if (is_numeric($headerName)) {
                $headerName = $headerValue;
                $headerValue = static::KEYWORD_PASSTHROUGH;
            }
            $headerValue = $this->resolveContent($headerValue);
            if ($headerValue === null) {
                continue;
            }
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

    protected function getDispatcher(): ?DataDispatcherInterface
    {
        $url = $this->getUrl();
        if (!$url) {
            $this->logger->error('No URL provided for request dispatcher');
            return null;
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
            $this->logger->error($e->getMessage());
            return null;
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

    public static function getDefaultConfiguration(): array
    {
        $config = [
                static::KEY_ENABLED => static::DEFAULT_ENABLED,
                static::KEY_URL => static::DEFAULT_URL,
                static::KEY_COOKIES => static::DEFAULT_COOKIES,
                static::KEY_HEADERS => static::DEFAULT_HEADERS,
            ]
            + parent::getDefaultConfiguration();
        $config[static::KEY_DATA][static::KEY_FIELDS] = static::DEFAULT_FIELDS;
        return $config;
    }
}
