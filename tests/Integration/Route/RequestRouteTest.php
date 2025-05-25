<?php

namespace DigitalMarketingFramework\Distributor\Request\Tests\Integration\Route;

use DigitalMarketingFramework\Core\Exception\DigitalMarketingFrameworkException;
use DigitalMarketingFramework\Core\Registry\RegistryDomain;
use DigitalMarketingFramework\Distributor\Core\Model\DataSet\SubmissionDataSetInterface;
use DigitalMarketingFramework\Distributor\Core\Tests\Integration\DistributorRegistryTestTrait;
use DigitalMarketingFramework\Distributor\Core\Tests\Integration\SubmissionTestTrait;
use DigitalMarketingFramework\Distributor\Request\DistributorRequestInitialization;
use DigitalMarketingFramework\Distributor\Request\Route\RequestOutboundRoute;
use DigitalMarketingFramework\Distributor\Request\Tests\Spy\DataDispatcher\RequestDataDispatcherSpyInterface;
use DigitalMarketingFramework\Distributor\Request\Tests\Spy\DataDispatcher\SpiedOnRequestDataDispatcher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(RequestOutboundRoute::class)]
class RequestRouteTest extends TestCase
{
    use DistributorRegistryTestTrait;
    use SubmissionTestTrait;

    protected RequestOutboundRoute $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->initRegistry();
        $initialization = new DistributorRequestInitialization();
        $initialization->initMetaData($this->registry);
        // NOTE use these init methods if the initalization object changes
        // $initialization->initGlobalConfiguration(RegistryDomain::CORE, $this->registry);
        // $initialization->initGlobalConfiguration(RegistryDomain::DISTRIBUTOR, $this->registry);
        // $initialization->initServices(RegistryDomain::CORE, $this->registry);
        // $initialization->initServices(RegistryDomain::DISTRIBUTOR, $this->registry);
        $initialization->initPlugins(RegistryDomain::CORE, $this->registry);
        $initialization->initPlugins(RegistryDomain::DISTRIBUTOR, $this->registry);

        $this->initSubmission();
    }

    protected function registerRequestDataDispatcherSpy(): RequestDataDispatcherSpyInterface&MockObject
    {
        /** @var RequestDataDispatcherSpyInterface&MockObject $spy */
        $spy = $this->createMock(RequestDataDispatcherSpyInterface::class);
        $this->registry->registerDataDispatcher(SpiedOnRequestDataDispatcher::class, [$spy], 'request');

        return $spy;
    }

    /**
     * @param array<string,string> $cookies
     * @param array<string,string> $headers
     */
    protected function configureRequest(string $ipAddress, array $cookies = [], array $headers = []): void
    {
        $this->context->expects($this->any())
            ->method('getCookies')
            ->willReturn($cookies);

        $this->context->expects($this->any())
            ->method('getIpAddress')
            ->willReturn($ipAddress);

        $requestVariableMap = [];
        foreach ($headers as $name => $value) {
            $requestVariableMap[] = [$name, $value];
        }

        $this->context->expects($this->any())
            ->method('getRequestVariable')
            ->willReturnMap($requestVariableMap);

        $this->context->expects($this->any())
            ->method('getRequestVariables')
            ->willReturn($headers);
    }

    protected function getRoute(SubmissionDataSetInterface $submission, string $routeId): RequestOutboundRoute
    {
        $route = $this->registry->getOutboundRoute($submission, 'request', $routeId);
        $this->assertInstanceOf(RequestOutboundRoute::class, $route);

        return $route;
    }

    #[Test]
    public function useConfiguredUrlAndPassData(): void
    {
        $dataDispatcherSpy = $this->registerRequestDataDispatcherSpy();
        $this->submissionData['field_a'] = 'value_a';

        $this->configurePassthroughDataMapperGroup('dataMapperGroupId1');

        $this->addRouteConfiguration('request', 'routeId1', 10, [
            'enabled' => true,
            'requiredPermission' => 'unregulated:allowed',
            'url' => 'https://my-endpoint.tld/api/foo',
            'data' => 'dataMapperGroupId1',
        ], integrationName: 'request');

        $this->configureRequest('', [], []);
        $submission = $this->getSubmission();
        $this->subject = $this->getRoute($submission, 'routeId1');

        // process context
        $this->subject->addContext($submission->getContext());
        $this->assertEmpty($submission->getContext()->getCookies());
        $this->assertEmpty($submission->getContext()->getRequestVariables());

        // process job
        $dataDispatcherSpy->expects($this->once())->method('setUrl')->with('https://my-endpoint.tld/api/foo');
        $dataDispatcherSpy->expects($this->once())->method('addCookies')->with([]);
        $dataDispatcherSpy->expects($this->once())->method('addHeaders')->with([]);
        $dataDispatcherSpy->expects($this->once())->method('send')->with(['field_a' => 'value_a']);
        $this->subject->process();
    }

    #[Test]
    public function throwExceptionWithoutConfiguredUrl(): void
    {
        $dataDispatcherSpy = $this->registerRequestDataDispatcherSpy();
        $dataDispatcherSpy->expects($this->never())->method('setUrl');
        $dataDispatcherSpy->expects($this->never())->method('send');

        $this->submissionData['field_a'] = 'value_a';

        $this->configurePassthroughDataMapperGroup('dataMapperGroupId1');

        $this->addRouteConfiguration('request', 'routeId1', 10, [
            'enabled' => true,
            'requiredPermission' => 'unregulated:allowed',
            'data' => 'dataMapperGroupId1',
        ], integrationName: 'request');

        $this->configureRequest('', [], []);
        $submission = $this->getSubmission();

        $this->subject = $this->getRoute($submission, 'routeId1');

        // process context
        $this->subject->addContext($submission->getContext());
        $this->assertEmpty($submission->getContext()->getCookies());
        $this->assertEmpty($submission->getContext()->getRequestVariables());

        // process job
        $this->expectException(DigitalMarketingFrameworkException::class);
        $this->subject->process();
    }

    // cookie functionality

    #[Test]
    public function passThroughCookiesAsPlainList(): void
    {
        $dataDispatcherSpy = $this->registerRequestDataDispatcherSpy();

        $this->submissionData['field_a'] = 'value_a';

        $this->configurePassthroughDataMapperGroup('dataMapperGroupId1');

        $this->addRouteConfiguration('request', 'routeId1', 10, [
            'enabled' => true,
            'requiredPermission' => 'unregulated:allowed',
            'url' => 'https://my-endpoint.tld/api/foo',
            'data' => 'dataMapperGroupId1',
            'cookies' => [
                'cookieItemId1' => $this->createMapItem('cookie1', '{value}', 'cookieItemId1', 10),
                'cookieItemId2' => $this->createMapItem('cookie2', '{value}', 'cookieItemId2', 20),
                'cookieItemId3' => $this->createMapItem('cookie3', '{value}', 'cookieItemId3', 30),
                'cookieItemId4' => $this->createMapItem('specialCookie.*', '{value}', 'cookieItemId4', 40),
            ],
        ], integrationName: 'request');

        $this->configureRequest(
            '',
            [
                'cookie1' => 'value1',
                'cookie3' => 'value3',
                'cookie4' => 'value4',
                'specialCookie5' => 'value5',
                'specialCookie6' => 'value6',
            ],
            []
        );

        $submission = $this->getSubmission();
        $this->subject = $this->getRoute($submission, 'routeId1');

        // process context
        $this->subject->addContext($submission->getContext());
        $this->assertEquals(
            [
                'cookie1' => 'value1',
                'cookie3' => 'value3',
                'specialCookie5' => 'value5',
                'specialCookie6' => 'value6',
            ],
            $submission->getContext()->getCookies()
        );
        $this->assertEmpty($submission->getContext()->getRequestVariables());

        // process job
        $dataDispatcherSpy->expects($this->once())->method('setUrl')->with('https://my-endpoint.tld/api/foo');
        $dataDispatcherSpy->expects($this->once())->method('addCookies')->with([
            'cookie1' => 'value1',
            'cookie3' => 'value3',
            'specialCookie5' => 'value5',
            'specialCookie6' => 'value6',
        ]);
        $dataDispatcherSpy->expects($this->once())->method('addHeaders')->with([]);
        $dataDispatcherSpy->expects($this->once())->method('send')->with(['field_a' => 'value_a']);
        $this->subject->process();
    }

    #[Test]
    public function defineCookiesWithAssocList(): void
    {
        $dataDispatcherSpy = $this->registerRequestDataDispatcherSpy();

        $this->submissionData['field_a'] = 'value_a';

        $this->configurePassthroughDataMapperGroup('dataMapperGroupId1');

        $this->addRouteConfiguration('request', 'routeId1', 10, [
            'enabled' => true,
            'requiredPermission' => 'unregulated:allowed',
            'url' => 'https://my-endpoint.tld/api/foo',
            'data' => 'dataMapperGroupId1',
            'cookies' => [
                'cookieItemId1' => $this->createMapItem('cookie1', '{value}', 'cookieItemId1', 10),
                'cookieItemId2' => $this->createMapItem('cookie2', 'value2b', 'cookieItemId2', 20),
                'cookieItemId3' => $this->createMapItem('cookie3', '{value}', 'cookieItemId3', 30),
                'cookieItemId4' => $this->createMapItem('specialCookie.*', '{value}', 'cookieItemId4', 40),
            ],
        ], integrationName: 'request');

        $this->configureRequest(
            '',
            [
                'cookie1' => 'value1',
                'cookie2' => 'value2',
                'cookie4' => 'value4',
                'specialCookie5' => 'value5',
                'specialCookie6' => 'value6',
            ],
            []
        );

        $submission = $this->getSubmission();
        $this->subject = $this->getRoute($submission, 'routeId1');

        // process context
        $this->subject->addContext($submission->getContext());
        $this->assertEquals(
            [
                'cookie1' => 'value1',
                'specialCookie5' => 'value5',
                'specialCookie6' => 'value6',
            ],
            $submission->getContext()->getCookies()
        );
        $this->assertEmpty($submission->getContext()->getRequestVariables());

        // process job
        $dataDispatcherSpy->expects($this->once())->method('setUrl')->with('https://my-endpoint.tld/api/foo');
        $dataDispatcherSpy->expects($this->once())->method('addCookies')->with([
            'cookie1' => 'value1',
            'cookie2' => 'value2b',
            'specialCookie5' => 'value5',
            'specialCookie6' => 'value6',
        ]);
        $dataDispatcherSpy->expects($this->once())->method('addHeaders')->with([]);
        $dataDispatcherSpy->expects($this->once())->method('send')->with(['field_a' => 'value_a']);
        $this->subject->process();
    }

    // header functionality

    #[Test]
    public function passThroughHeadersAsPlainList(): void
    {
        $dataDispatcherSpy = $this->registerRequestDataDispatcherSpy();

        $this->submissionData['field_a'] = 'value_a';

        $this->configurePassthroughDataMapperGroup('dataMapperGroupId1');

        $this->addRouteConfiguration('request', 'routeId1', 10, [
            'enabled' => true,
            'requiredPermission' => 'unregulated:allowed',
            'url' => 'https://my-endpoint.tld/api/foo',
            'data' => 'dataMapperGroupId1',
            'headers' => [
                'headerItemId1' => $this->createMapItem('header1', '{value}', 'headerItemId1', 10),
                'headerItemId2' => $this->createMapItem('header2', '{value}', 'headerItemId2', 20),
                'headerItemId3' => $this->createMapItem('header3', '{value}', 'headerItemId3', 30),
            ],
        ], integrationName: 'request');

        $this->configureRequest(
            '',
            [],
            [
                'header1' => 'value1',
                'header3' => 'value3',
                'header4' => 'value4',
            ]
        );

        $submission = $this->getSubmission();
        $this->subject = $this->getRoute($submission, 'routeId1');

        // process context
        $this->subject->addContext($submission->getContext());
        $this->assertEmpty($submission->getContext()->getCookies());
        $this->assertEquals(
            [
                'header1' => 'value1',
                'header3' => 'value3',
            ],
            $submission->getContext()->getRequestVariables()
        );

        // process job
        $dataDispatcherSpy->expects($this->once())->method('setUrl')->with('https://my-endpoint.tld/api/foo');
        $dataDispatcherSpy->expects($this->once())->method('addCookies')->with([]);
        $dataDispatcherSpy->expects($this->once())->method('addHeaders')->with([
            'header1' => 'value1',
            'header3' => 'value3',
        ]);

        $dataDispatcherSpy->expects($this->once())->method('send')->with(['field_a' => 'value_a']);
        $this->subject->process();
    }

    #[Test]
    public function defineHeadersWithAssocList(): void
    {
        $dataDispatcherSpy = $this->registerRequestDataDispatcherSpy();

        $this->submissionData['field_a'] = 'value_a';

        $this->configurePassthroughDataMapperGroup('dataMapperGroupId1');

        $this->addRouteConfiguration('request', 'routeId1', 10, [
            'enabled' => true,
            'requiredPermission' => 'unregulated:allowed',
            'url' => 'https://my-endpoint.tld/api/foo',
            'data' => 'dataMapperGroupId1',
            'headers' => [
                'headerItemId1' => $this->createMapItem('header1', '{value}', 'headerItemId1', 10),
                'headerItemId2' => $this->createMapItem('header2', 'value2b', 'headerItemId2', 20),
                'headerItemId3' => $this->createMapItem('header3', '{value}', 'headerItemId3', 30),
            ],
        ], integrationName: 'request');

        $this->configureRequest(
            '',
            [],
            [
                'header1' => 'value1',
                'header2' => 'value2',
                'header4' => 'value4',
            ]
        );

        $submission = $this->getSubmission();
        $this->subject = $this->getRoute($submission, 'routeId1');

        // process context
        $this->subject->addContext($submission->getContext());
        $this->assertEmpty($submission->getContext()->getCookies());
        $this->assertEquals(
            [
                'header1' => 'value1',
            ],
            $submission->getContext()->getRequestVariables()
        );

        // process job
        $dataDispatcherSpy->expects($this->once())->method('setUrl')->with('https://my-endpoint.tld/api/foo');
        $dataDispatcherSpy->expects($this->once())->method('addCookies')->with([]);
        $dataDispatcherSpy->expects($this->once())->method('addHeaders')->with([
            'header1' => 'value1',
            'header2' => 'value2b',
        ]);
        $dataDispatcherSpy->expects($this->once())->method('send')->with(['field_a' => 'value_a']);
        $this->subject->process();
    }

    #[Test]
    public function useInternalHeaderNames(): void
    {
        $dataDispatcherSpy = $this->registerRequestDataDispatcherSpy();

        $this->submissionData['field_a'] = 'value_a';

        $this->configurePassthroughDataMapperGroup('dataMapperGroupId1');

        $this->addRouteConfiguration('request', 'routeId1', 10, [
            'enabled' => true,
            'requiredPermission' => 'unregulated:allowed',
            'url' => 'https://my-endpoint.tld/api/foo',
            'data' => 'dataMapperGroupId1',
            'headers' => [
                'headerItemId1' => $this->createMapItem('Custom-Header', '{value}', 'headerItemId1', 10),
                'headerItemId2' => $this->createMapItem('User-Agent', '{value}', 'headerItemId2', 20),
                'headerItemId3' => $this->createMapItem('Content-Type', '{value}', 'headerItemId3', 30),
            ],
        ], integrationName: 'request');

        $this->configureRequest(
            '',
            [],
            [
                'Custom-Header' => 'value1',
                'HTTP_USER_AGENT' => 'value2',
                'CONTENT_TYPE' => 'value3',
            ]
        );

        $submission = $this->getSubmission();
        $this->subject = $this->getRoute($submission, 'routeId1');

        // process context
        $this->subject->addContext($submission->getContext());
        $this->assertEmpty($submission->getContext()->getCookies());
        $this->assertEquals(
            [
                'Custom-Header' => 'value1',
                'HTTP_USER_AGENT' => 'value2',
                'CONTENT_TYPE' => 'value3',
            ],
            $submission->getContext()->getRequestVariables()
        );

        // process job
        $dataDispatcherSpy->expects($this->once())->method('setUrl')->with('https://my-endpoint.tld/api/foo');
        $dataDispatcherSpy->expects($this->once())->method('addCookies')->with([]);
        $dataDispatcherSpy->expects($this->once())->method('addHeaders')->with([
            'Custom-Header' => 'value1',
            'User-Agent' => 'value2',
            'Content-Type' => 'value3',
        ]);
        $dataDispatcherSpy->expects($this->once())->method('send')->with(['field_a' => 'value_a']);
        $this->subject->process();
    }
}
