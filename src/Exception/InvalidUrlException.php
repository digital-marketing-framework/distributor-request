<?php

namespace DigitalMarketingFramework\Distributor\Request\Exception;

use DigitalMarketingFramework\Core\Exception\DigitalMarketingFrameworkException;

class InvalidUrlException extends DigitalMarketingFrameworkException
{
    public function __construct(string $url)
    {
        parent::__construct(sprintf('Bad URL %s', $url), 1565612422);
    }
}
