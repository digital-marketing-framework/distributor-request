<?php

namespace DigitalMarketingFramework\Distributor\Request\ResponseValidation;

use DigitalMarketingFramework\Core\Exception\DigitalMarketingFrameworkException;
use Psr\Http\Message\ResponseInterface;

/**
 * Default response validation: treats any non-2xx/3xx status code as a failure.
 *
 * Registered on every request data dispatcher by default. Routes that need to handle
 * status codes differently can remove it via clearResponseValidations() or replace the
 * set via setResponseValidations().
 */
class StatusCodeResponseValidation implements ResponseValidationInterface
{
    public function validate(ResponseInterface $response): void
    {
        $statusCode = $response->getStatusCode();
        if ($statusCode < 200 || $statusCode >= 400) {
            throw new DigitalMarketingFrameworkException('Status code: ' . $statusCode);
        }
    }
}
