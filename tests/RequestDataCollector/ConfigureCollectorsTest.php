<?php
declare(strict_types=1);

namespace Miquido\RequestDataCollector\Tests\RequestDataCollector;

use Miquido\RequestDataCollector\Collectors\Contracts\ConfigurableInterface;
use Miquido\RequestDataCollector\Collectors\Contracts\DataCollectorInterface;
use Miquido\RequestDataCollector\Collectors\Contracts\ModifiesContainerInterface;
use Miquido\RequestDataCollector\RequestDataCollector;
use Prophecy\Prophecy\ObjectProphecy;

/**
 * @covers \Miquido\RequestDataCollector\RequestDataCollector::configureCollectors
 * @coversDefaultClass \Miquido\RequestDataCollector\RequestDataCollector
 */
class ConfigureCollectorsTest extends AbstractRequestDataCollectorTest
{
    public function testDisabledCollectorIsSkipped(): void
    {
        $disabledCollectorDriver = '\\Test\\Collector\\1';

        $this->containerProphecy->make($disabledCollectorDriver)
            ->shouldNotBeCalled();

        $this->assertXRequestIdHeaderIsNotChecked();

        $requestDataCollector = new RequestDataCollector(
            $this->containerMock,
            $this->logManagerMock,
            $this->requestMock,
            [
                'channel' => 'some-channel-name',

                'collectors' => [
                    'disabled-collector' => false,
                ],

                'options' => [
                    'disabled-collector' => [
                        'driver' => $disabledCollectorDriver,
                    ],
                ],
            ]
        );

        self::assertFalse($requestDataCollector->hasCollector('disabled-collector'));
    }

    public function testBasicCollectorIsConfigured(): void
    {
        $basicCollectorDriver = '\\Test\\Collector\\1';

        $this->assertCollectorWasRegisteredAndReturns($basicCollectorDriver);

        $this->assertXRequestIdHeaderIsNotChecked();

        $requestDataCollector = new RequestDataCollector(
            $this->containerMock,
            $this->logManagerMock,
            $this->requestMock,
            [
                'channel' => 'some-channel-name',

                'collectors' => [
                    'basic-collector' => true,
                ],

                'options' => [
                    'basic-collector' => [
                        'driver' => $basicCollectorDriver,
                    ],
                ],
            ]
        );

        self::assertTrue($requestDataCollector->hasCollector('basic-collector'));
    }

    public function testConfigurableCollectorReceivesConfig(): void
    {
        $config = [
            'driver' => '\\Test\\Collector\\1',
            'foo'    => 'bar',
        ];

        /**
         * @var \Miquido\RequestDataCollector\Collectors\Contracts\ConfigurableInterface $dataCollectorProphecy
         */
        $dataCollectorProphecy = $this->assertCollectorWasRegisteredAndReturns($config['driver'], [ConfigurableInterface::class]);

        $this->assertXRequestIdHeaderIsNotChecked();

        $dataCollectorProphecy->setConfig($config)
            ->shouldBeCalledOnce();

        $requestDataCollector = new RequestDataCollector(
            $this->containerMock,
            $this->logManagerMock,
            $this->requestMock,
            [
                'channel' => 'some-channel-name',

                'collectors' => [
                    'configurable-collector' => true,
                ],

                'options' => [
                    'configurable-collector' => $config,
                ],
            ]
        );

        self::assertTrue($requestDataCollector->hasCollector('configurable-collector'));
    }

    public function testCollectorThatModifiesContainerIsRegistered(): void
    {
        $modifyingContainerCollectorDriver = '\\Test\\Collector\\1';

        /**
         * @var \Miquido\RequestDataCollector\Collectors\Contracts\ModifiesContainerInterface $dataCollectorProphecy
         */
        $dataCollectorProphecy = $this->assertCollectorWasRegisteredAndReturns($modifyingContainerCollectorDriver, [ModifiesContainerInterface::class]);

        $this->assertXRequestIdHeaderIsNotChecked();

        $dataCollectorProphecy->register($this->containerMock)
            ->shouldBeCalledOnce();

        $requestDataCollector = new RequestDataCollector(
            $this->containerMock,
            $this->logManagerMock,
            $this->requestMock,
            [
                'channel' => 'some-channel-name',

                'collectors' => [
                    'modifying-container-collector' => true,
                ],

                'options' => [
                    'modifying-container-collector' => [
                        'driver' => $modifyingContainerCollectorDriver,
                    ],
                ],
            ]
        );

        self::assertTrue($requestDataCollector->hasCollector('modifying-container-collector'));
    }

    /**
     * @param string[] $implements
     *
     * @return \Miquido\RequestDataCollector\Collectors\Contracts\DataCollectorInterface|\Prophecy\Prophecy\ObjectProphecy
     */
    private function assertCollectorWasRegisteredAndReturns(string $driver, array $implements = []): ObjectProphecy
    {
        /**
         * @var \Miquido\RequestDataCollector\Collectors\Contracts\DataCollectorInterface|\Prophecy\Prophecy\ObjectProphecy $dataCollectorProphecy
         */
        $dataCollectorProphecy = $this->prophesize(DataCollectorInterface::class);

        foreach ($implements as $interface) {
            $dataCollectorProphecy->willImplement($interface);
        }

        $this->containerProphecy->make($driver)
            ->shouldBeCalledOnce()
            ->willReturn($dataCollectorProphecy->reveal());

        return $dataCollectorProphecy;
    }
}
