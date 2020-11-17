<?php
declare(strict_types=1);

namespace Miquido\RequestDataCollector\Tests\RequestDataCollector;

use Miquido\RequestDataCollector\Collectors\Contracts\DataCollectorInterface;
use Miquido\RequestDataCollector\Collectors\Contracts\SupportsSeparateLogEntriesInterface;
use Miquido\RequestDataCollector\Collectors\Contracts\UsesResponseInterface;
use Miquido\RequestDataCollector\RequestDataCollector;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * @covers \Miquido\RequestDataCollector\RequestDataCollector::collect
 * @coversDefaultClass \Miquido\RequestDataCollector\RequestDataCollector
 */
class CollectTest extends AbstractRequestDataCollectorTest
{
    use ProphecyTrait;

    /**
     * @var \Prophecy\Prophecy\ObjectProphecy|\Psr\Log\LoggerInterface
     */
    private $loggerProphecy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->loggerProphecy = $this->prophesize(LoggerInterface::class);
    }

    public function testCollectNothingWhenNoCollectorsDefined(): void
    {
        $this->assertXRequestIdHeaderIsNotChecked();

        $requestDataCollector = new RequestDataCollector(
            $this->containerMock,
            $this->logManagerMock,
            $this->requestMock,
            [
                'channel'    => 'some-channel-name',
                'collectors' => [],
                'options'    => [],
            ]
        );

        $this->logManagerProphecy->channel('some-channel-name')
            ->shouldBeCalledOnce()
            ->willReturn($this->loggerProphecy->reveal());

        $this->loggerProphecy->debug(Argument::any(), Argument::any())
            ->shouldNotBeCalled();

        $requestDataCollector->collect($this->makeResponseDummy());
    }

    public function testSimpleEntriesAreLoggedWhenCollectorsCollectedNothing(): void
    {
        $channelName = 'some-channel-name';
        $testCollector1Driver = '\\Test\\Collector\\1';
        $testCollector2Driver = '\\Test\\Collector\\2';
        $testCollector3Driver = '\\Test\\Collector\\3';

        $responseDummy = $this->makeResponseDummy();

        $this->assertCollectorWasRegisteredAndReturns($testCollector1Driver, []);
        $this->assertCollectorSupportingSeparateLogEntriesWasRegisteredAndReturns($testCollector2Driver, []);
        $this->assertCollectorThatUsesResponseWasRegisteredAndReturns($testCollector3Driver, $responseDummy, []);

        $this->assertXRequestIdHeaderIsNotChecked();

        $requestDataCollector = new RequestDataCollector(
            $this->containerMock,
            $this->logManagerMock,
            $this->requestMock,
            [
                'channel' => $channelName,

                'collectors' => [
                    'test-collector-1' => true,
                    'test-collector-2' => true,
                    'test-collector-3' => true,
                ],

                'options' => [
                    'test-collector-1' => [
                        'driver' => $testCollector1Driver,
                    ],

                    'test-collector-2' => [
                        'driver' => $testCollector2Driver,
                    ],

                    'test-collector-3' => [
                        'driver' => $testCollector3Driver,
                    ],
                ],
            ]
        );

        $this->logManagerProphecy->channel($channelName)
            ->shouldBeCalledOnce()
            ->willReturn($this->loggerProphecy->reveal());

        $this->loggerProphecy->debug(\sprintf('request-data-collector.%s.%s', 'test-collector-1', $requestDataCollector->getRequestId()), [])
            ->shouldBeCalledOnce();

        $this->loggerProphecy->debug(\sprintf('request-data-collector.%s.%s', 'test-collector-2', $requestDataCollector->getRequestId()), [])
            ->shouldBeCalledOnce();

        $this->loggerProphecy->debug(\sprintf('request-data-collector.%s.%s', 'test-collector-3', $requestDataCollector->getRequestId()), [])
            ->shouldBeCalledOnce();

        $requestDataCollector->collect($responseDummy);
    }

    public function testLogEntryIsCreatedForBasicCollector(): void
    {
        $channelName = 'some-channel-name';
        $testCollector1Driver = '\\Test\\Collector\\1';
        $testCollector1Collected = [1, 2, 3];

        $responseDummy = $this->makeResponseDummy();

        $this->assertCollectorWasRegisteredAndReturns($testCollector1Driver, $testCollector1Collected);

        $this->assertXRequestIdHeaderIsNotChecked();

        $requestDataCollector = new RequestDataCollector(
            $this->containerMock,
            $this->logManagerMock,
            $this->requestMock,
            [
                'channel' => $channelName,

                'collectors' => [
                    'test-collector-1' => true,
                ],

                'options' => [
                    'test-collector-1' => [
                        'driver' => $testCollector1Driver,
                    ],
                ],
            ]
        );

        $this->logManagerProphecy->channel($channelName)
            ->shouldBeCalledOnce()
            ->willReturn($this->loggerProphecy->reveal());

        $this->loggerProphecy->debug(\sprintf('request-data-collector.test-collector-1.%s', $requestDataCollector->getRequestId()), $testCollector1Collected)
            ->shouldBeCalledOnce();

        $requestDataCollector->collect($responseDummy);
    }

    public function testSingleLogEntryIsCreatedForSeparateCollectorWhenGlobalLoggingFormatIsSingleAndIsInherited(): void
    {
        $channelName = 'some-channel-name';
        $testCollector1Driver = '\\Test\\Collector\\1';
        $testCollector1Collected = [1, 2, 3];

        $responseDummy = $this->makeResponseDummy();

        $dataCollectorProphecy = $this->assertCollectorSupportingSeparateLogEntriesWasRegisteredAndReturns($testCollector1Driver, $testCollector1Collected);

        $this->assertXRequestIdHeaderIsNotChecked();

        $requestDataCollector = new RequestDataCollector(
            $this->containerMock,
            $this->logManagerMock,
            $this->requestMock,
            [
                'channel'        => $channelName,
                'logging_format' => RequestDataCollector::LOGGING_FORMAT_SINGLE,

                'collectors' => [
                    'test-collector-1' => true,
                ],

                'options' => [
                    'test-collector-1' => [
                        'driver'         => $testCollector1Driver,
                        'logging_format' => null,
                    ],
                ],
            ]
        );

        $this->logManagerProphecy->channel($channelName)
            ->shouldBeCalledOnce()
            ->willReturn($this->loggerProphecy->reveal());

        $dataCollectorProphecy->getConfig('logging_format')
            ->shouldBeCalledOnce()
            ->willReturn(null);

        $this->loggerProphecy->debug(\sprintf('request-data-collector.test-collector-1.%s', $requestDataCollector->getRequestId()), $testCollector1Collected)
            ->shouldBeCalledOnce();

        $requestDataCollector->collect($responseDummy);
    }

    public function testSingleLogEntryIsCreatedForSeparateCollectorWhenGlobalLoggingFormatIsSeparateButCollectorLoggingFormatIsSingle(): void
    {
        $channelName = 'some-channel-name';
        $testCollector1Driver = '\\Test\\Collector\\1';
        $testCollector1Collected = [1, 2, 3];

        $responseDummy = $this->makeResponseDummy();

        $dataCollectorProphecy = $this->assertCollectorSupportingSeparateLogEntriesWasRegisteredAndReturns($testCollector1Driver, $testCollector1Collected);

        $this->assertXRequestIdHeaderIsNotChecked();

        $requestDataCollector = new RequestDataCollector(
            $this->containerMock,
            $this->logManagerMock,
            $this->requestMock,
            [
                'channel'        => $channelName,
                'logging_format' => RequestDataCollector::LOGGING_FORMAT_SEPARATE,

                'collectors' => [
                    'test-collector-1' => true,
                ],

                'options' => [
                    'test-collector-1' => [
                        'driver'         => $testCollector1Driver,
                        'logging_format' => RequestDataCollector::LOGGING_FORMAT_SINGLE,
                    ],
                ],
            ]
        );

        $this->logManagerProphecy->channel($channelName)
            ->shouldBeCalledOnce()
            ->willReturn($this->loggerProphecy->reveal());

        $dataCollectorProphecy->getConfig('logging_format')
            ->shouldBeCalledOnce()
            ->willReturn(RequestDataCollector::LOGGING_FORMAT_SINGLE);

        $this->loggerProphecy->debug(\sprintf('request-data-collector.test-collector-1.%s', $requestDataCollector->getRequestId()), $testCollector1Collected)
            ->shouldBeCalledOnce();

        $requestDataCollector->collect($responseDummy);
    }

    public function testSeparateLogEntriesAreCreatedForSeparateCollectorWhenGlobalLoggingFormatIsSeparateAndIsInherited(): void
    {
        $channelName = 'some-channel-name';
        $testCollector1Driver = '\\Test\\Collector\\1';
        $testCollector1Collected = [1, 2, 3];

        $responseDummy = $this->makeResponseDummy();

        $dataCollectorProphecy = $this->assertCollectorSupportingSeparateLogEntriesWasRegisteredAndReturns($testCollector1Driver, $testCollector1Collected);

        $this->assertXRequestIdHeaderIsNotChecked();

        $requestDataCollector = new RequestDataCollector(
            $this->containerMock,
            $this->logManagerMock,
            $this->requestMock,
            [
                'channel'        => $channelName,
                'logging_format' => RequestDataCollector::LOGGING_FORMAT_SEPARATE,

                'collectors' => [
                    'test-collector-1' => true,
                ],

                'options' => [
                    'test-collector-1' => [
                        'driver'         => $testCollector1Driver,
                        'logging_format' => null,
                    ],
                ],
            ]
        );

        $this->logManagerProphecy->channel($channelName)
            ->shouldBeCalledOnce()
            ->willReturn($this->loggerProphecy->reveal());

        $dataCollectorProphecy->getConfig('logging_format')
            ->shouldBeCalledOnce()
            ->willReturn(null);

        $separatedCollectedData = [
            'foo' => ['Lorem ipsum'],
            'bar' => [123],
            'baz' => ['foo', 123],
        ];

        $dataCollectorProphecy->getSeparateLogEntries($testCollector1Collected)
            ->shouldBeCalledOnce()
            ->willReturn($separatedCollectedData);

        foreach ($separatedCollectedData as $key => $value) {
            $this->loggerProphecy->debug(\sprintf('request-data-collector.test-collector-1.%s.%s', $key, $requestDataCollector->getRequestId()), $value)
                ->shouldBeCalledOnce();
        }

        $requestDataCollector->collect($responseDummy);
    }

    public function testSeparateLogEntriesAreCreatedForSeparateCollectorWhenGlobalLoggingFormatIsSingleButCollectorLoggingFormatIsSeparate(): void
    {
        $channelName = 'some-channel-name';
        $testCollector1Driver = '\\Test\\Collector\\1';
        $testCollector1Collected = [1, 2, 3];

        $responseDummy = $this->makeResponseDummy();

        $dataCollectorProphecy = $this->assertCollectorSupportingSeparateLogEntriesWasRegisteredAndReturns($testCollector1Driver, $testCollector1Collected);

        $this->assertXRequestIdHeaderIsNotChecked();

        $requestDataCollector = new RequestDataCollector(
            $this->containerMock,
            $this->logManagerMock,
            $this->requestMock,
            [
                'channel'        => $channelName,
                'logging_format' => RequestDataCollector::LOGGING_FORMAT_SINGLE,

                'collectors' => [
                    'test-collector-1' => true,
                ],

                'options' => [
                    'test-collector-1' => [
                        'driver'         => $testCollector1Driver,
                        'logging_format' => RequestDataCollector::LOGGING_FORMAT_SEPARATE,
                    ],
                ],
            ]
        );

        $this->logManagerProphecy->channel($channelName)
            ->shouldBeCalledOnce()
            ->willReturn($this->loggerProphecy->reveal());

        $dataCollectorProphecy->getConfig('logging_format')
            ->shouldBeCalledOnce()
            ->willReturn(RequestDataCollector::LOGGING_FORMAT_SEPARATE);

        $separatedCollectedData = [
            'foo' => ['Lorem ipsum'],
            'bar' => [123],
            'baz' => ['foo', 123],
        ];

        $dataCollectorProphecy->getSeparateLogEntries($testCollector1Collected)
            ->shouldBeCalledOnce()
            ->willReturn($separatedCollectedData);

        foreach ($separatedCollectedData as $key => $value) {
            $this->loggerProphecy->debug(\sprintf('request-data-collector.test-collector-1.%s.%s', $key, $requestDataCollector->getRequestId()), $value)
                ->shouldBeCalledOnce();
        }

        $requestDataCollector->collect($responseDummy);
    }

    private function makeResponseDummy(): Response
    {
        return $this->prophesize(Response::class)->reveal();
    }

    /**
     * @param string[] $implements
     *
     * @return \Miquido\RequestDataCollector\Collectors\Contracts\DataCollectorInterface|\Prophecy\Prophecy\ObjectProphecy
     */
    private function assertCollectorWasRegisteredAndReturns(string $driver, array $collected, array $implements = []): ObjectProphecy
    {
        /**
         * @var \Miquido\RequestDataCollector\Collectors\Contracts\DataCollectorInterface|\Prophecy\Prophecy\ObjectProphecy $dataCollectorProphecy
         */
        $dataCollectorProphecy = $this->prophesize(DataCollectorInterface::class);

        foreach ($implements as $interface) {
            $dataCollectorProphecy->willImplement($interface);
        }

        $dataCollectorProphecy->collect()
            ->shouldBeCalledOnce()
            ->willReturn($collected);

        $this->containerProphecy->make($driver)
            ->shouldBeCalledOnce()
            ->willReturn($dataCollectorProphecy->reveal());

        return $dataCollectorProphecy;
    }

    /**
     * @return \Miquido\RequestDataCollector\Collectors\Contracts\DataCollectorInterface|\Miquido\RequestDataCollector\Collectors\Contracts\UsesResponseInterface|\Prophecy\Prophecy\ObjectProphecy
     */
    private function assertCollectorThatUsesResponseWasRegisteredAndReturns(string $driver, Response $response, array $collected): ObjectProphecy
    {
        /**
         * @var \Miquido\RequestDataCollector\Collectors\Contracts\DataCollectorInterface|\Miquido\RequestDataCollector\Collectors\Contracts\UsesResponseInterface|\Prophecy\Prophecy\ObjectProphecy $dataCollectorProphecy
         */
        $dataCollectorProphecy = $this->assertCollectorWasRegisteredAndReturns($driver, $collected, [
            UsesResponseInterface::class,
        ]);

        $dataCollectorProphecy->setResponse($response)
            ->shouldBeCalledOnce();

        return $dataCollectorProphecy;
    }

    /**
     * @return \Miquido\RequestDataCollector\Collectors\Contracts\DataCollectorInterface|\Miquido\RequestDataCollector\Collectors\Contracts\SupportsSeparateLogEntriesInterface|\Prophecy\Prophecy\ObjectProphecy
     */
    private function assertCollectorSupportingSeparateLogEntriesWasRegisteredAndReturns(string $driver, array $collected): ObjectProphecy
    {
        /**
         * @var \Miquido\RequestDataCollector\Collectors\Contracts\DataCollectorInterface|\Miquido\RequestDataCollector\Collectors\Contracts\SupportsSeparateLogEntriesInterface|\Prophecy\Prophecy\ObjectProphecy $dataCollectorProphecy
         */
        $dataCollectorProphecy = $this->assertCollectorWasRegisteredAndReturns($driver, $collected, [
            SupportsSeparateLogEntriesInterface::class,
        ]);

        $dataCollectorProphecy->setConfig(Argument::type('array'))
            ->shouldBeCalledOnce();

        return $dataCollectorProphecy;
    }
}
