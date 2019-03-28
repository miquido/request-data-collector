<?php
declare(strict_types=1);

namespace Tests;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Log\LogManager;
use Miquido\RequestDataCollector\Collectors\Contracts\ConfigurableInterface;
use Miquido\RequestDataCollector\Collectors\Contracts\DataCollectorInterface;
use Miquido\RequestDataCollector\Collectors\Contracts\ModifiesContainerInterface;
use Miquido\RequestDataCollector\Collectors\Contracts\UsesResponseInterface;
use Miquido\RequestDataCollector\Filters\Contracts\FilterInterface;
use Miquido\RequestDataCollector\RequestDataCollector;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * @coversDefaultClass \Miquido\RequestDataCollector\RequestDataCollector
 */
class RequestDataCollectorTest extends TestCase
{
    /**
     * @var \Illuminate\Contracts\Foundation\Application&\Prophecy\Prophecy\ObjectProphecy
     */
    private $applicationProphecy;

    /**
     * @var \Illuminate\Log\LogManager&\Prophecy\Prophecy\ObjectProphecy
     */
    private $logManagerProphecy;

    /**
     * @var \Illuminate\Contracts\Foundation\Application
     */
    private $applicationMock;

    /**
     * @var \Illuminate\Log\LogManager
     */
    private $logManagerMock;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        $this->applicationProphecy = $this->prophesize(Application::class);
        $this->logManagerProphecy = $this->prophesize(LogManager::class);

        $this->applicationMock = $this->applicationProphecy->reveal();
        $this->logManagerMock = $this->logManagerProphecy->reveal();
    }

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

    /**
     * @dataProvider validEnabledOptionDataProvider
     *
     * @param bool $enabled
     */
    public function testCreateRequestDataCollectorWithWorkingState(bool $enabled): void
    {
        $requestDataCollector = new RequestDataCollector($this->applicationMock, $this->logManagerMock, [
            'enabled'    => $enabled,
            'collectors' => [],
        ]);

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

        $this->applicationProphecy->make($testCollector1Driver)
            ->shouldBeCalledOnce()
            ->willReturn($testCollector1Dummy);

        $this->applicationProphecy->make($testCollector2Driver)
            ->shouldBeCalledOnce()
            ->willReturn($testCollector2Dummy);

        $testCollector2Prophecy->setConfig(['driver' => $testCollector2Driver])
            ->shouldBeCalledOnce();

        $this->applicationProphecy->make($testCollector3Driver)
            ->shouldBeCalledOnce()
            ->willReturn($testCollector3Dummy);

        $testCollector3Prophecy->register($this->applicationMock)
            ->shouldBeCalledOnce();

        $this->applicationProphecy->make($testCollector4Driver)
            ->shouldNotBeCalled();

        $requestDataCollector = new RequestDataCollector($this->applicationMock, $this->logManagerMock, [
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
        ]);

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

        $requestDataCollector = new RequestDataCollector($this->applicationMock, $this->logManagerMock, [
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
        ]);

        /**
         * @var \Miquido\RequestDataCollector\Filters\Contracts\FilterInterface&\Prophecy\Prophecy\ObjectProphecy $filter1Prophecy
         * @var \Miquido\RequestDataCollector\Filters\Contracts\FilterInterface&\Prophecy\Prophecy\ObjectProphecy $filter2Prophecy
         */
        $filter1Prophecy = $this->prophesize(FilterInterface::class);
        $filter2Prophecy = $this->prophesize(FilterInterface::class);

        $this->applicationProphecy->make($filter1Class, $filter1Options)
            ->shouldBeCalledTimes(3)
            ->willReturn($filter1Prophecy->reveal());

        $this->applicationProphecy->make($filter2Class, $filter2Options)
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

        $this->applicationProphecy->make($testCollector1Driver)
            ->shouldBeCalledOnce()
            ->willReturn($testCollector1Dummy);

        $this->applicationProphecy->make($testCollector2Driver)
            ->shouldBeCalledOnce()
            ->willReturn($testCollector2Dummy);

        $requestDataCollector = new RequestDataCollector($this->applicationMock, $this->logManagerMock, [
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
        ]);

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
}