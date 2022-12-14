<?php

namespace DigitalMarketingFramework\Distributor\Request\Tests\Integration\Route;

use DigitalMarketingFramework\Core\Exception\DigitalMarketingFrameworkException;
use DigitalMarketingFramework\Core\Log\LoggerInterface;
use DigitalMarketingFramework\Distributor\Core\Tests\Integration\RegistryTestTrait;
use DigitalMarketingFramework\Distributor\Core\Tests\Integration\SubmissionTestTrait;
use DigitalMarketingFramework\Distributor\Request\DistributorPluginInitialization;
use DigitalMarketingFramework\Distributor\Request\DistributorRouteInitialization;
use DigitalMarketingFramework\Distributor\Request\Route\RequestRoute;
use DigitalMarketingFramework\Distributor\Request\Tests\Spy\DataDispatcher\RequestDataDispatcherSpyInterface;
use DigitalMarketingFramework\Distributor\Request\Tests\Spy\DataDispatcher\SpiedOnRequestDataDispatcher;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/** @covers RequestRoute */
class RequestRouteTest extends TestCase
{
    use RegistryTestTrait;
    use SubmissionTestTrait;

    protected LoggerInterface&MockObject $logger;

    protected RequestRoute $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->initRegistry();
        DistributorPluginInitialization::initialize($this->registry);
        DistributorRouteInitialization::initialize($this->registry);

        $this->logger = $this->createMock(LoggerInterface::class);
        
        $this->initSubmission();
    }

    protected function registerRequestDataDispatcherSpy(): RequestDataDispatcherSpyInterface&MockObject
    {
        $spy = $this->createMock(RequestDataDispatcherSpyInterface::class);
        $this->registry->registerDataDispatcher(SpiedOnRequestDataDispatcher::class, [$spy], 'request');
        return $spy;
    }

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
    }

    /** @test */
    public function useConfiguredUrlAndPassData(): void
    {
        $dataDispatcherSpy = $this->registerRequestDataDispatcherSpy();

        $this->addRouteConfiguration('request', [
            'enabled' => true,
            'url' => 'https://my-endpoint.tld/api/foo',
            'data' => [
                'fields' => [
                    'field_a' => 'value_a',
                ],
            ],
        ]);

        $this->configureRequest('', [], []);
        $submission = $this->getSubmission();
        $this->subject = new RequestRoute('request', $this->registry, $submission, 0);
        $this->subject->setLogger($this->logger);
        
        // process context
        $this->subject->addContext($this->context);
        $this->assertEmpty($submission->getContext()->getCookies());
        $this->assertEmpty($submission->getContext()->getRequestVariables());

        // process job
        $dataDispatcherSpy->expects($this->once())->method('setUrl')->with('https://my-endpoint.tld/api/foo');
        $dataDispatcherSpy->expects($this->once())->method('addCookies')->with([]);
        $dataDispatcherSpy->expects($this->once())->method('addHeaders')->with([]);
        $dataDispatcherSpy->expects($this->once())->method('send')->with(['field_a' => 'value_a']);
        $this->subject->process();
    }

    /** @test */
    public function throwExceptionWithoutConfiguredUrl(): void
    {
        $dataDispatcherSpy = $this->registerRequestDataDispatcherSpy();
        $dataDispatcherSpy->expects($this->never())->method('setUrl');
        $dataDispatcherSpy->expects($this->never())->method('send');

        $this->addRouteConfiguration('request', [
            'enabled' => true,
            'data' => [
                'fields' => [
                    'field_a' => 'value_a',
                ],
            ],
        ]);

        $this->configureRequest('', [], []);
        $submission = $this->getSubmission();
        $this->subject = new RequestRoute('request', $this->registry, $submission, 0);
        $this->subject->setLogger($this->logger);
        
        // process context
        $this->subject->addContext($this->context);
        $this->assertEmpty($submission->getContext()->getCookies());
        $this->assertEmpty($submission->getContext()->getRequestVariables());

        $this->logger->expects($this->once())->method('error')->with('No URL provided for request dispatcher');

        // process job
        $this->expectException(DigitalMarketingFrameworkException::class);
        $this->subject->process();
    }
    
    // cookie functionality

    /** @test */
    public function passThroughCookiesAsPlainList(): void
    {
        $dataDispatcherSpy = $this->registerRequestDataDispatcherSpy();

        $this->addRouteConfiguration('request', [
            'enabled' => true,
            'url' => 'https://my-endpoint.tld/api/foo',
            'data' => [
                'fields' => [
                    'field_a' => 'value_a',
                ],
            ],
            'cookies' => [
                'cookie1',
                'cookie2',
                'cookie3',
                'specialCookie.*',
            ],
        ]);

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
        $this->subject = new RequestRoute('request', $this->registry, $submission, 0);
        $this->subject->setLogger($this->logger);
        
        // process context
        $this->subject->addContext($this->context);
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

    /** @test */
    public function defineCookiesWithAssocList(): void
    {
        $dataDispatcherSpy = $this->registerRequestDataDispatcherSpy();

        $this->addRouteConfiguration('request', [
            'enabled' => true,
            'url' => 'https://my-endpoint.tld/api/foo',
            'data' => [
                'fields' => [
                    'field_a' => 'value_a',
                ],
            ],
            'cookies' => [
                'cookie1' => '__PASSTHROUGH',
                'cookie2' => 'value2b',
                'cookie3' => '__PASSTHROUGH',
                'specialCookie.*' => '__PASSTHROUGH',
            ],
        ]);

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
        $this->subject = new RequestRoute('request', $this->registry, $submission, 0);
        $this->subject->setLogger($this->logger);
        
        // process context
        $this->subject->addContext($this->context);
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

    /** @test */
    public function useContentResolverAsCookieValue(): void
    {
        $dataDispatcherSpy = $this->registerRequestDataDispatcherSpy();

        $this->submissionData['field_a'] = 'value_a';

        $this->addRouteConfiguration('request', [
            'enabled' => true,
            'url' => 'https://my-endpoint.tld/api/foo',
            'data' => [
                'fields' => [
                    'field_a' => ['field' => 'field_a'],
                ],
            ],
            'cookies' => [
                'cookie1' => [
                    'field' => 'field_a',
                ],
                'cookie2' => [
                    'if' => [
                        'field_a' => 'value_a',
                        'then' => '__PASSTHROUGH',
                    ],
                ],
                'cookie3' => [
                    'if' => [
                        'field_a' => 'value_b',
                        'then' => '__PASSTHROUGH',
                    ],
                ],
                'cookie4' => [
                    'if' => [
                        'field_a' => 'value_b',
                        'else' => 'value_c',
                    ],
                ],
            ],
        ]);

        $this->configureRequest(
            '', 
            [
                'cookie1' => 'value1',
                'cookie2' => 'value2',
                'cookie3' => 'value3',
                'cookie4' => 'value4',
            ], 
            []
        );

        $submission = $this->getSubmission();
        $this->subject = new RequestRoute('request', $this->registry, $submission, 0);
        $this->subject->setLogger($this->logger);
        
        // process context
        $this->subject->addContext($this->context);
        $this->assertEquals(
            [
                'cookie2' => 'value2',
            ],
            $submission->getContext()->getCookies()
        );
        $this->assertEmpty($submission->getContext()->getRequestVariables());

        // process job
        $dataDispatcherSpy->expects($this->once())->method('setUrl')->with('https://my-endpoint.tld/api/foo');
        $dataDispatcherSpy->expects($this->once())->method('addCookies')->with([
            'cookie1' => 'value_a',
            'cookie2' => 'value2',
            'cookie4' => 'value_c',
        ]);
        $dataDispatcherSpy->expects($this->once())->method('addHeaders')->with([]);
        $dataDispatcherSpy->expects($this->once())->method('send')->with(['field_a' => 'value_a']);
        $this->subject->process();
    }

    // header functionality

    /** @test */
    public function passThroughHeadersAsPlainList()
    {
        $dataDispatcherSpy = $this->registerRequestDataDispatcherSpy();

        $this->addRouteConfiguration('request', [
            'enabled' => true,
            'url' => 'https://my-endpoint.tld/api/foo',
            'data' => [
                'fields' => [
                    'field_a' => 'value_a',
                ],
            ],
            'headers' => [
                'header1',
                'header2',
                'header3',
            ],
        ]);

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
        $this->subject = new RequestRoute('request', $this->registry, $submission, 0);
        
        // process context
        $this->subject->addContext($this->context);
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

    // /** @test */
    public function defineHeadersWithAssocList()
    {
        $dataDispatcherSpy = $this->registerRequestDataDispatcherSpy();

        $this->addRouteConfiguration('request', [
            'enabled' => true,
            'url' => 'https://my-endpoint.tld/api/foo',
            'data' => [
                'fields' => [
                    'field_a' => 'value_a',
                ],
            ],
            'headers' => [
                'header1' => '__PASSTHROUGH',
                'header2' => 'value2b',
                'header3' => '__PASSTHROUGH',
            ],
        ]);

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
        $this->subject = new RequestRoute('request', $this->registry, $submission, 0);
        $this->subject->setLogger($this->logger);
        
        // process context
        $this->subject->addContext($this->context);
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

    /** @test */
    public function useContentResolverAsHeaderValue()
    {
        $dataDispatcherSpy = $this->registerRequestDataDispatcherSpy();

        $this->submissionData['field_a'] = 'value_a';

        $this->addRouteConfiguration('request', [
            'enabled' => true,
            'url' => 'https://my-endpoint.tld/api/foo',
            'data' => [
                'fields' => [
                    'field_a' => ['field' => 'field_a'],
                ],
            ],
            'headers' => [
                'header1' => [
                    'field' => 'field_a',
                ],
                'header2' => [
                    'if' => [
                        'field_a' => 'value_a',
                        'then' => '__PASSTHROUGH',
                    ],
                ],
                'header3' => [
                    'if' => [
                        'field_a' => 'value_b',
                        'then' => '__PASSTHROUGH',
                    ],
                ],
                'header4' => [
                    'if' => [
                        'field_a' => 'value_b',
                        'else' => 'value_c',
                    ],
                ],
            ],
        ]);

        $this->configureRequest(
            '', 
            [], 
            [
                'header1' => 'value1',
                'header2' => 'value2',
                'header3' => 'value3',
                'header4' => 'value4',
            ]
        );

        $submission = $this->getSubmission();
        $this->subject = new RequestRoute('request', $this->registry, $submission, 0);
        $this->subject->setLogger($this->logger);
        
        // process context
        $this->subject->addContext($this->context);
        $this->assertEmpty($submission->getContext()->getCookies());
        $this->assertEquals(
            [
                'header2' => 'value2',
            ],
            $submission->getContext()->getRequestVariables()
        );

        // process job
        $dataDispatcherSpy->expects($this->once())->method('setUrl')->with('https://my-endpoint.tld/api/foo');
        $dataDispatcherSpy->expects($this->once())->method('addCookies')->with([]);
        $dataDispatcherSpy->expects($this->once())->method('addHeaders')->with([
            'header1' => 'value_a',
            'header2' => 'value2',
            'header4' => 'value_c',
        ]);
        $dataDispatcherSpy->expects($this->once())->method('send')->with(['field_a' => 'value_a']);
        $this->subject->process();
    }

    /** @test */
    public function useInternalHeaderNames()
    {
        $dataDispatcherSpy = $this->registerRequestDataDispatcherSpy();

        $this->submissionData['field_a'] = 'value_a';

        $this->addRouteConfiguration('request', [
            'enabled' => true,
            'url' => 'https://my-endpoint.tld/api/foo',
            'data' => [
                'fields' => [
                    'field_a' => ['field' => 'field_a'],
                ],
            ],
            'headers' => [
                'Custom-Header',
                'User-Agent',
                'Content-Type',
            ],
        ]);

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
        $this->subject = new RequestRoute('request', $this->registry, $submission, 0);
        $this->subject->setLogger($this->logger);
        
        // process context
        $this->subject->addContext($this->context);
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
