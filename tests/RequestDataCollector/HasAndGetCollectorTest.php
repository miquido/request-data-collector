<?php
declare(strict_types=1);

namespace Miquido\RequestDataCollector\Tests\RequestDataCollector;

use Miquido\RequestDataCollector\Collectors\Contracts\ConfigurableInterface;
use Miquido\RequestDataCollector\Collectors\Contracts\DataCollectorInterface;
use Miquido\RequestDataCollector\Collectors\Contracts\ModifiesContainerInterface;
use Miquido\RequestDataCollector\RequestDataCollector;

/**
 * @covers \Miquido\RequestDataCollector\RequestDataCollector
 * @coversDefaultClass \Miquido\RequestDataCollector\RequestDataCollector
 */
class HasAndGetCollectorTest extends AbstractRequestDataCollectorTest
{
    public function testHasAndGetCollector(): void
    {
        $testCollector1Driver = '\\Test\\Collector\\1';
        $testCollector2Driver = '\\Test\\Collector\\2';
        $testCollector3Driver = '\\Test\\Collector\\3';
        $testCollector4Driver = '\\Test\\Collector\\4';

        /**
         * @var \Miquido\RequestDataCollector\Collectors\Contracts\DataCollectorInterface|\Prophecy\Prophecy\ObjectProphecy $testCollector1Prophecy
         */
        $testCollector1Prophecy = $this->prophesize(DataCollectorInterface::class);

        /**
         * @var \Miquido\RequestDataCollector\Collectors\Contracts\ConfigurableInterface|\Miquido\RequestDataCollector\Collectors\Contracts\DataCollectorInterface|\Prophecy\Prophecy\ObjectProphecy $testCollector2Prophecy
         */
        $testCollector2Prophecy = $this->prophesize(DataCollectorInterface::class)->willImplement(ConfigurableInterface::class);

        /**
         * @var \Miquido\RequestDataCollector\Collectors\Contracts\DataCollectorInterface|\Miquido\RequestDataCollector\Collectors\Contracts\ModifiesContainerInterface|\Prophecy\Prophecy\ObjectProphecy $testCollector3Prophecy
         */
        $testCollector3Prophecy = $this->prophesize(DataCollectorInterface::class)->willImplement(ModifiesContainerInterface::class);

        /**
         * @var \Miquido\RequestDataCollector\Collectors\Contracts\DataCollectorInterface $testCollector1Dummy
         */
        $testCollector1Dummy = $testCollector1Prophecy->reveal();

        /**
         * @var \Miquido\RequestDataCollector\Collectors\Contracts\ConfigurableInterface|\Miquido\RequestDataCollector\Collectors\Contracts\DataCollectorInterface $testCollector2Dummy
         */
        $testCollector2Dummy = $testCollector2Prophecy->reveal();

        /**
         * @var \Miquido\RequestDataCollector\Collectors\Contracts\DataCollectorInterface|\Miquido\RequestDataCollector\Collectors\Contracts\ModifiesContainerInterface $testCollector3Dummy
         */
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
}
