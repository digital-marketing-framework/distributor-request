<?php

namespace DigitalMarketingFramework\Distributor\Request\Tests\Unit\ResponseValidation;

use DigitalMarketingFramework\Core\Exception\DigitalMarketingFrameworkException;
use DigitalMarketingFramework\Distributor\Request\ResponseValidation\StatusCodeResponseValidation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

#[CoversClass(StatusCodeResponseValidation::class)]
class StatusCodeResponseValidationTest extends TestCase
{
    protected function response(int $statusCode): ResponseInterface
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn($statusCode);

        return $response;
    }

    #[Test]
    public function passesOnSuccessAndRedirectStatusCodes(): void
    {
        $validation = new StatusCodeResponseValidation();
        $validation->validate($this->response(200));
        $validation->validate($this->response(204));
        $validation->validate($this->response(302));
        $this->addToAssertionCount(1);
    }

    #[Test]
    public function throwsOnClientErrorStatusCode(): void
    {
        $this->expectException(DigitalMarketingFrameworkException::class);
        (new StatusCodeResponseValidation())->validate($this->response(400));
    }

    #[Test]
    public function throwsOnServerErrorStatusCode(): void
    {
        $this->expectException(DigitalMarketingFrameworkException::class);
        (new StatusCodeResponseValidation())->validate($this->response(500));
    }
}
