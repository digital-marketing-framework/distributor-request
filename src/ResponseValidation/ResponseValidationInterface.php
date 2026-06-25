<?php

namespace DigitalMarketingFramework\Distributor\Request\ResponseValidation;

use DigitalMarketingFramework\Core\Exception\DigitalMarketingFrameworkException;
use Psr\Http\Message\ResponseInterface;

/**
 * A check applied to the HTTP response of a request data dispatcher.
 *
 * Routes can register their own validations on the dispatcher to interpret responses
 * that the default status-code check cannot (e.g. APIs that always answer 2xx but
 * report problems in the body).
 */
interface ResponseValidationInterface
{
    /**
     * @throws DigitalMarketingFrameworkException when the response is considered invalid
     */
    public function validate(ResponseInterface $response): void;
}
