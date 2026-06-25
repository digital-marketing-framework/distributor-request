<?php

namespace DigitalMarketingFramework\Distributor\Request\Tests\Unit\DataDispatcher;

use DigitalMarketingFramework\Core\Model\Data\Value\MultiValue;
use DigitalMarketingFramework\Distributor\Core\Model\Data\Value\DiscreteMultiValue;
use DigitalMarketingFramework\Distributor\Core\Registry\RegistryInterface;
use DigitalMarketingFramework\Distributor\Request\DataDispatcher\RequestDataDispatcher;
use DigitalMarketingFramework\Distributor\Request\ResponseValidation\ResponseValidationInterface;
use DigitalMarketingFramework\Distributor\Request\ResponseValidation\StatusCodeResponseValidation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(RequestDataDispatcher::class)]
class RequestDataDispatcherTest extends TestCase
{
    protected RequestDataDispatcher $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $registry = $this->createMock(RegistryInterface::class);
        $this->subject = new RequestDataDispatcher('request', $registry);
        $this->subject->setUrl('https://example.com/api');
    }

    // -- Flat mode (default) --

    #[Test]
    public function flatModeScalarValues(): void
    {
        $this->subject->setMethod('POST');
        $preview = $this->subject->preview(['foo' => 'bar', 'baz' => 'qux']);
        $this->assertSame('foo=bar&baz=qux', $preview['body']);
    }

    #[Test]
    public function flatModeMultiValueJoinsWithGlue(): void
    {
        $this->subject->setMethod('POST');
        $multi = new MultiValue(['red', 'blue']);
        $preview = $this->subject->preview(['colors' => $multi]);
        $this->assertSame('colors=red%2Cblue', $preview['body']);
    }

    #[Test]
    public function flatModeDiscreteMultiValueRepeatsKey(): void
    {
        $this->subject->setMethod('POST');
        $discrete = new DiscreteMultiValue(['red', 'blue']);
        $preview = $this->subject->preview(['colors' => $discrete]);
        $this->assertSame('colors=red&colors=blue', $preview['body']);
    }

    // -- Nested mode --

    #[Test]
    public function nestedModeScalarValues(): void
    {
        $this->subject->setMethod('POST');
        $this->subject->setMultiValueFormat(RequestDataDispatcher::MULTI_VALUE_FORMAT_NESTED);

        $preview = $this->subject->preview(['foo' => 'bar', 'baz' => 'qux']);
        $this->assertSame('foo=bar&baz=qux', $preview['body']);
    }

    #[Test]
    public function nestedModeMultiValueUsesBrackets(): void
    {
        $this->subject->setMethod('POST');
        $this->subject->setMultiValueFormat(RequestDataDispatcher::MULTI_VALUE_FORMAT_NESTED);

        $multi = new MultiValue(['red', 'blue']);
        $preview = $this->subject->preview(['colors' => $multi]);
        $this->assertSame('colors%5B0%5D=red&colors%5B1%5D=blue', $preview['body']);
    }

    #[Test]
    public function nestedModeDiscreteMultiValueRepeatsKey(): void
    {
        $this->subject->setMethod('POST');
        $this->subject->setMultiValueFormat(RequestDataDispatcher::MULTI_VALUE_FORMAT_NESTED);

        $discrete = new DiscreteMultiValue(['red', 'blue']);
        $preview = $this->subject->preview(['colors' => $discrete]);
        $this->assertSame('colors=red&colors=blue', $preview['body']);
    }

    #[Test]
    public function nestedModeMultiValueNestedInMultiValue(): void
    {
        $this->subject->setMethod('POST');
        $this->subject->setMultiValueFormat(RequestDataDispatcher::MULTI_VALUE_FORMAT_NESTED);

        $inner1 = new MultiValue(['a', 'b']);
        $inner2 = new MultiValue(['c']);
        $outer = new MultiValue([$inner1, $inner2]);
        $preview = $this->subject->preview(['data' => $outer]);
        $this->assertSame('data%5B0%5D%5B0%5D=a&data%5B0%5D%5B1%5D=b&data%5B1%5D%5B0%5D=c', $preview['body']);
    }

    #[Test]
    public function nestedModeDiscreteMultiValueNestedInMultiValue(): void
    {
        $this->subject->setMethod('POST');
        $this->subject->setMultiValueFormat(RequestDataDispatcher::MULTI_VALUE_FORMAT_NESTED);

        $inner1 = new DiscreteMultiValue(['a', 'b']);
        $inner2 = new DiscreteMultiValue(['c', 'd']);
        $outer = new MultiValue([$inner1, $inner2]);
        $preview = $this->subject->preview(['tags' => $outer]);
        $this->assertSame('tags%5B0%5D=a&tags%5B0%5D=b&tags%5B1%5D=c&tags%5B1%5D=d', $preview['body']);
    }

    #[Test]
    public function nestedModeAssociativeMultiValue(): void
    {
        $this->subject->setMethod('POST');
        $this->subject->setMultiValueFormat(RequestDataDispatcher::MULTI_VALUE_FORMAT_NESTED);

        $multi = new MultiValue(['first' => 'John', 'last' => 'Doe']);
        $preview = $this->subject->preview(['name' => $multi]);
        $this->assertSame('name%5Bfirst%5D=John&name%5Blast%5D=Doe', $preview['body']);
    }

    // -- GET requests --

    #[Test]
    public function getRequestAppendsQueryString(): void
    {
        $this->subject->setMethod('GET');
        $preview = $this->subject->preview(['foo' => 'bar', 'baz' => 'qux']);
        $this->assertSame('https://example.com/api?foo=bar&baz=qux', $preview['query']);
        $this->assertArrayNotHasKey('body', $preview);
    }

    #[Test]
    public function getRequestAppendsToExistingQueryString(): void
    {
        $this->subject->setUrl('https://example.com/api?existing=param');
        $this->subject->setMethod('GET');

        $preview = $this->subject->preview(['foo' => 'bar']);
        $this->assertSame('https://example.com/api?existing=param&foo=bar', $preview['query']);
    }

    #[Test]
    public function getRequestNestedMode(): void
    {
        $this->subject->setMethod('GET');
        $this->subject->setMultiValueFormat(RequestDataDispatcher::MULTI_VALUE_FORMAT_NESTED);

        $multi = new MultiValue(['red', 'blue']);
        $preview = $this->subject->preview(['colors' => $multi]);
        $this->assertSame('https://example.com/api?colors%5B0%5D=red&colors%5B1%5D=blue', $preview['query']);
    }

    #[Test]
    public function getRequestEmptyData(): void
    {
        $this->subject->setMethod('GET');
        $preview = $this->subject->preview([]);
        $this->assertSame('https://example.com/api', $preview['query']);
    }

    // -- Headers --

    #[Test]
    public function getRequestOmitsContentType(): void
    {
        $this->subject->setMethod('GET');
        $preview = $this->subject->preview(['foo' => 'bar']);
        $this->assertArrayHasKey('Accept', $preview['headers']);
        $this->assertArrayNotHasKey('Content-Type', $preview['headers']);
    }

    #[Test]
    public function postRequestIncludesContentType(): void
    {
        $this->subject->setMethod('POST');
        $preview = $this->subject->preview(['foo' => 'bar']);
        $this->assertSame('application/x-www-form-urlencoded', $preview['headers']['Content-Type']);
    }

    // -- Multi-value format setting --

    #[Test]
    public function multiValueFormatDefaultsToFlat(): void
    {
        $this->assertSame(RequestDataDispatcher::MULTI_VALUE_FORMAT_FLAT, $this->subject->getMultiValueFormat());
    }

    #[Test]
    public function multiValueFormatCanBeSet(): void
    {
        $this->subject->setMultiValueFormat(RequestDataDispatcher::MULTI_VALUE_FORMAT_NESTED);
        $this->assertSame(RequestDataDispatcher::MULTI_VALUE_FORMAT_NESTED, $this->subject->getMultiValueFormat());
    }

    // -- Response validations --

    #[Test]
    public function statusCodeValidationIsRegisteredByDefault(): void
    {
        $validations = $this->subject->getResponseValidations();
        $this->assertCount(1, $validations);
        $this->assertInstanceOf(StatusCodeResponseValidation::class, $validations[0]);
    }

    #[Test]
    public function clearResponseValidationsRemovesAll(): void
    {
        $this->subject->clearResponseValidations();
        $this->assertSame([], $this->subject->getResponseValidations());
    }

    #[Test]
    public function addResponseValidationAppends(): void
    {
        $extra = $this->createMock(ResponseValidationInterface::class);
        $this->subject->addResponseValidation($extra);

        $validations = $this->subject->getResponseValidations();
        $this->assertCount(2, $validations);
        $this->assertSame($extra, $validations[1]);
    }

    #[Test]
    public function setResponseValidationsReplacesTheWholeSet(): void
    {
        $custom = $this->createMock(ResponseValidationInterface::class);
        $this->subject->setResponseValidations([$custom]);
        $this->assertSame([$custom], $this->subject->getResponseValidations());
    }
}
