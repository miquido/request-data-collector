<?php
declare(strict_types=1);

namespace Miquido\RequestDataCollector\Tests;

use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Log\LogManager;
use Miquido\RequestDataCollector\Collectors\Contracts\ConfigurableInterface;
use Miquido\RequestDataCollector\Collectors\Contracts\DataCollectorInterface;
use Miquido\RequestDataCollector\Collectors\Contracts\ModifiesContainerInterface;
use Miquido\RequestDataCollector\Collectors\Contracts\UsesResponseInterface;
use Miquido\RequestDataCollector\Filters\Contracts\FilterInterface;
use Miquido\RequestDataCollector\RequestDataCollector;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * @coversDefaultClass \Miquido\RequestDataCollector\RequestDataCollector
 */
class RequestDataCollectorTest extends TestCase
{
    /**
     * @var \Illuminate\Contracts\Container\Container&\Prophecy\Prophecy\ObjectProphecy
     */
    private $containerProphecy;

    /**
     * @var \Illuminate\Log\LogManager&\Prophecy\Prophecy\ObjectProphecy
     */
    private $logManagerProphecy;

    /**
     * @var \Illuminate\Http\Request&\Prophecy\Prophecy\ObjectProphecy
     */
    private $requestProphecy;

    /**
     * @var \Illuminate\Contracts\Foundation\Application
     */
    private $containerMock;

    /**
     * @var \Illuminate\Log\LogManager
     */
    private $logManagerMock;

    /**
     * @var \Illuminate\Http\Request
     */
    private $requestMock;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        $this->containerProphecy = $this->prophesize(Container::class);
        $this->logManagerProphecy = $this->prophesize(LogManager::class);
        $this->requestProphecy = $this->prophesize(Request::class);

        $this->containerMock = $this->containerProphecy->reveal();
        $this->logManagerMock = $this->logManagerProphecy->reveal();
        $this->requestMock = $this->requestProphecy->reveal();
    }

    /**
     * Provides a set of valid values for 'enabled' option.
     *
     * @return array
     */
    public function validEnabledOptionDataProvider(): array
    {
        return [
            'Enabled' => [
                'enabled' => true,
            ],

            'Disabled' => [
                'enabled' => false,
            ],
        ];
    }

    public function testStartedAt(): void
    {
        self::assertSame(-1.0, RequestDataCollector::getStartedAt());

        $this->assertXRequestIdHeaderIsNotChecked();

        $startedAt = \microtime(true);

        new RequestDataCollector(
            $this->containerMock,
            $this->logManagerMock,
            $this->requestMock,
            [
                'enabled'    => false,
                'collectors' => [],
            ]
        );

        self::assertEqualsWithDelta($startedAt, RequestDataCollector::getStartedAt(), 0.001);
    }

    /**
     * @dataProvider validEnabledOptionDataProvider
     *
     * @param bool $enabled
     */
    public function testCreateRequestDataCollectorWithWorkingState(bool $enabled): void
    {
        $this->assertXRequestIdHeaderIsNotChecked();

        $requestDataCollector = new RequestDataCollector(
            $this->containerMock,
            $this->logManagerMock,
            $this->requestMock,
            [
                'enabled'    => $enabled,
                'collectors' => [],
            ]
        );

        self::assertSame($enabled, $requestDataCollector->isEnabled());
    }

    public function testCollectorGetterAndSetter(): void
    {
        $testCollector1Driver = '\\Test\\Collector\\1';
        $testCollector2Driver = '\\Test\\Collector\\2';
        $testCollector3Driver = '\\Test\\Collector\\3';
        $testCollector4Driver = '\\Test\\Collector\\4';

        /**
         * @var \Miquido\RequestDataCollector\Collectors\Contracts\DataCollectorInterface&\Prophecy\Prophecy\ObjectProphecy                                                                               $testCollector1Prophecy
         * @var \Miquido\RequestDataCollector\Collectors\Contracts\DataCollectorInterface&\Miquido\RequestDataCollector\Collectors\Contracts\ConfigurableInterface&\Prophecy\Prophecy\ObjectProphecy      $testCollector2Prophecy
         * @var \Miquido\RequestDataCollector\Collectors\Contracts\DataCollectorInterface&\Miquido\RequestDataCollector\Collectors\Contracts\ModifiesContainerInterface&\Prophecy\Prophecy\ObjectProphecy $testCollector3Prophecy
         * @var \Miquido\RequestDataCollector\Collectors\Contracts\DataCollectorInterface                                                                                                                 $testCollector1Dummy
         * @var \Miquido\RequestDataCollector\Collectors\Contracts\DataCollectorInterface&ConfigurableInterface                                                                                           $testCollector2Dummy
         * @var \Miquido\RequestDataCollector\Collectors\Contracts\DataCollectorInterface&\Miquido\RequestDataCollector\Collectors\Contracts\ModifiesContainerInterface                                   $testCollector3Dummy
         */
        $testCollector1Prophecy = $this->prophesize(DataCollectorInterface::class);
        $testCollector2Prophecy = $this->prophesize(DataCollectorInterface::class)->willImplement(ConfigurableInterface::class);
        $testCollector3Prophecy = $this->prophesize(DataCollectorInterface::class)->willImplement(ModifiesContainerInterface::class);
        $testCollector1Dummy = $testCollector1Prophecy->reveal();
        $testCollector2Dummy = $testCollector2Prophecy->reveal();
        $testCollector3Dummy = $testCollector3Prophecy->reveal();

        $this->containerProphecy->make($testCollector1Driver)
            ->shouldBeCalledOnce()
            ->willReturn($testCollector1Dummy);

        $this->containerProphecy->make($testCollector2Driver)
            ->shouldBeCalledOnce()
            ->willReturn($testCollector2Dummy);

        $testCollector2Prophecy->setConfig(['driver' => $testCollector2Driver])
            ->shouldBeCalledOnce();

        $this->containerProphecy->make($testCollector3Driver)
            ->shouldBeCalledOnce()
            ->willReturn($testCollector3Dummy);

        $testCollector3Prophecy->register($this->containerMock)
            ->shouldBeCalledOnce();

        $this->containerProphecy->make($testCollector4Driver)
            ->shouldNotBeCalled();

        $this->assertXRequestIdHeaderIsNotChecked();

        $requestDataCollector = new RequestDataCollector(
            $this->containerMock,
            $this->logManagerMock,
            $this->requestMock,
            [
                'collectors' => [
                    'test-collector-1' => true,
                    'test-collector-2' => true,
                    'test-collector-3' => true,
                    'test-collector-4' => false,
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

                    'test-collector-4' => [
                        'driver' => $testCollector4Driver,
                    ],
                ],
            ]
        );

        // "test-collector-1/2/3" exists because it is enabled
        self::assertTrue($requestDataCollector->hasCollector('test-collector-1'));
        self::assertTrue($requestDataCollector->hasCollector('test-collector-2'));
        self::assertTrue($requestDataCollector->hasCollector('test-collector-3'));

        // "test-collector-1" doesn't exist because it is disabled
        self::assertFalse($requestDataCollector->hasCollector('test-collector-4'));

        self::assertSame($testCollector1Dummy, $requestDataCollector->getCollector('test-collector-1'));
        self::assertSame($testCollector2Dummy, $requestDataCollector->getCollector('test-collector-2'));
        self::assertSame($testCollector3Dummy, $requestDataCollector->getCollector('test-collector-3'));
    }

    public function testIsRequestExcluded(): void
    {
        $filter1Class = '\\Test\\Filter\\1';
        $filter1Options = [1, 2, 3];
        $filter2Class = '\\Test\\Filter\\2';
        $filter2Options = [4, 5, 6];

        $this->assertXRequestIdHeaderIsNotChecked();

        $requestDataCollector = new RequestDataCollector(
            $this->containerMock,
            $this->logManagerMock,
            $this->requestMock,
            [
                'collectors' => [],

                'exclude' => [
                    [
                        'filter' => $filter1Class,
                        'with'   => $filter1Options,
                    ],
                    [
                        'filter' => $filter2Class,
                        'with'   => $filter2Options,
                    ],
                ],
            ]
        );

        /**
         * @var \Miquido\RequestDataCollector\Filters\Contracts\FilterInterface&\Prophecy\Prophecy\ObjectProphecy $filter1Prophecy
         * @var \Miquido\RequestDataCollector\Filters\Contracts\FilterInterface&\Prophecy\Prophecy\ObjectProphecy $filter2Prophecy
         */
        $filter1Prophecy = $this->prophesize(FilterInterface::class);
        $filter2Prophecy = $this->prophesize(FilterInterface::class);

        $this->containerProphecy->make($filter1Class, $filter1Options)
            ->shouldBeCalledTimes(3)
            ->willReturn($filter1Prophecy->reveal());

        $this->containerProphecy->make($filter2Class, $filter2Options)
            ->shouldBeCalledTimes(2)
            ->willReturn($filter2Prophecy->reveal());

        /**
         * @var \Illuminate\Http\Request $request1Dummy
         * @var \Illuminate\Http\Request $request2Dummy
         * @var \Illuminate\Http\Request $request3Dummy
         */
        $request1Dummy = $this->prophesize(Request::class)->reveal();
        $request2Dummy = $this->prophesize(Request::class)->reveal();
        $request3Dummy = $this->prophesize(Request::class)->reveal();

        $filter1Prophecy->accept($request1Dummy)
            ->shouldBeCalledOnce()
            ->willReturn(true);

        $filter2Prophecy->accept($request1Dummy)
            ->shouldNotBeCalled();

        $filter1Prophecy->accept($request2Dummy)
            ->shouldBeCalledOnce()
            ->willReturn(false);

        $filter2Prophecy->accept($request2Dummy)
            ->shouldBeCalledOnce()
            ->willReturn(true);

        $filter1Prophecy->accept($request3Dummy)
            ->shouldBeCalledOnce()
            ->willReturn(false);

        $filter2Prophecy->accept($request3Dummy)
            ->shouldBeCalledOnce()
            ->willReturn(false);

        self::assertTrue($requestDataCollector->isRequestExcluded($request1Dummy));
        self::assertTrue($requestDataCollector->isRequestExcluded($request2Dummy));
        self::assertFalse($requestDataCollector->isRequestExcluded($request3Dummy));
    }

    public function testCollect(): void
    {
        $testCollector1Driver = '\\Test\\Collector\\1';
        $testCollector1Collected = [1, 2, 3];
        $testCollector2Driver = '\\Test\\Collector\\2';
        $testCollector2Collected = [4, 5, 6];

        /**
         * @var \Miquido\RequestDataCollector\Collectors\Contracts\DataCollectorInterface&\Prophecy\Prophecy\ObjectProphecy                                                                          $testCollector1Prophecy
         * @var \Miquido\RequestDataCollector\Collectors\Contracts\DataCollectorInterface&\Miquido\RequestDataCollector\Collectors\Contracts\UsesResponseInterface&\Prophecy\Prophecy\ObjectProphecy $testCollector2Prophecy
         * @var \Miquido\RequestDataCollector\Collectors\Contracts\DataCollectorInterface                                                                                                            $testCollector1Dummy
         * @var \Miquido\RequestDataCollector\Collectors\Contracts\DataCollectorInterface                                                                                                            $testCollector2Dummy
         */
        $testCollector1Prophecy = $this->prophesize(DataCollectorInterface::class);
        $testCollector2Prophecy = $this->prophesize(DataCollectorInterface::class)->willImplement(UsesResponseInterface::class);
        $testCollector1Dummy = $testCollector1Prophecy->reveal();
        $testCollector2Dummy = $testCollector2Prophecy->reveal();

        $this->containerProphecy->make($testCollector1Driver)
            ->shouldBeCalledOnce()
            ->willReturn($testCollector1Dummy);

        $this->containerProphecy->make($testCollector2Driver)
            ->shouldBeCalledOnce()
            ->willReturn($testCollector2Dummy);

        $this->assertXRequestIdHeaderIsNotChecked();

        $requestDataCollector = new RequestDataCollector(
            $this->containerMock,
            $this->logManagerMock,
            $this->requestMock,
            [
                'channel' => 'some-channel-name',

                'collectors' => [
                    'test-collector-1' => true,
                    'test-collector-2' => true,
                ],

                'options' => [
                    'test-collector-1' => [
                        'driver' => $testCollector1Driver,
                    ],

                    'test-collector-2' => [
                        'driver' => $testCollector2Driver,
                    ],
                ],
            ]
        );

        /**
         * @var \Symfony\Component\HttpFoundation\Response $responseDummy
         * @var \Psr\Log\LoggerInterface                   $loggerProphecy
         */
        $responseDummy = $this->prophesize(Response::class)->reveal();
        $loggerProphecy = $this->prophesize(LoggerInterface::class);

        $this->logManagerProphecy->channel('some-channel-name')
            ->shouldBeCalledOnce()
            ->willReturn($loggerProphecy->reveal());

        $testCollector1Prophecy->collect()
            ->shouldBeCalledOnce()
            ->willReturn($testCollector1Collected);

        $loggerProphecy->debug(\sprintf('request-data-collector.%s.%s', 'test-collector-1', $requestDataCollector->getRequestId()), $testCollector1Collected)
            ->shouldBeCalledOnce();

        $testCollector2Prophecy->setResponse($responseDummy)
            ->shouldBeCalledOnce();

        $testCollector2Prophecy->collect()
            ->shouldBeCalledOnce()
            ->willReturn($testCollector2Collected);

        $loggerProphecy->debug(\sprintf('request-data-collector.%s.%s', 'test-collector-2', $requestDataCollector->getRequestId()), $testCollector2Collected)
            ->shouldBeCalledOnce();

        $requestDataCollector->collect($responseDummy);
    }

    public function testGetRequestIdFromHeaderWhenTrackingIsEnabled(): void
    {
        $xRequestId = 'X0123456789abcdef0123456789abcdef';

        $this->requestProphecy->header('x-request-id', '')
            ->shouldBeCalledOnce()
            ->willReturn($xRequestId);

        $requestDataCollector = new RequestDataCollector(
            $this->containerMock,
            $this->logManagerMock,
            $this->requestMock,
            [
                'enabled'    => true,
                'tracking'   => true,
                'collectors' => [],
            ]
        );

        self::assertSame($xRequestId, $requestDataCollector->getRequestId());
    }

    public function testDoNotGetRequestIdFromHeaderWhenTrackingIsDisabled(): void
    {
        $xRequestId = 'X0123456789abcdef0123456789abcdef';

        $this->requestProphecy->header('x-request-id', '')
            ->willReturn($xRequestId);

        $this->assertXRequestIdHeaderIsNotChecked();

        $requestDataCollector = new RequestDataCollector(
            $this->containerMock,
            $this->logManagerMock,
            $this->requestMock,
            [
                'enabled'    => true,
                'tracking'   => false,
                'collectors' => [],
            ]
        );

        self::assertNotEquals($xRequestId, $requestDataCollector->getRequestId());
    }

    public function testSetInvalidRequestId(): void
    {
        $this->assertXRequestIdHeaderIsNotChecked();

        $requestDataCollector = new RequestDataCollector(
            $this->containerMock,
            $this->logManagerMock,
            $this->requestMock,
            [
                'enabled'    => true,
                'collectors' => [],
            ]
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The Request ID has invalid format');

        $requestDataCollector->setRequestId('invalid-request-id');
    }

    public function testSetValidRequestId(): void
    {
        $xRequestId = 'X0123456789abcdef0123456789abcdef';

        $this->assertXRequestIdHeaderIsNotChecked();

        $requestDataCollector = new RequestDataCollector(
            $this->containerMock,
            $this->logManagerMock,
            $this->requestMock,
            [
                'enabled'    => true,
                'collectors' => [],
            ]
        );

        $requestDataCollector->setRequestId($xRequestId);

        self::assertSame($xRequestId, $requestDataCollector->getRequestId());
    }

    private function assertXRequestIdHeaderIsNotChecked(): void
    {
        $this->requestProphecy->header('x-request-id', Argument::any())
            ->shouldNotBeCalled();
    }
}
