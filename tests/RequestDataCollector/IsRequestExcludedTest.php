<?php
declare(strict_types=1);

namespace Miquido\RequestDataCollector\Tests\RequestDataCollector;

use Illuminate\Http\Request;
use Miquido\RequestDataCollector\Filters\Contracts\FilterInterface;
use Miquido\RequestDataCollector\RequestDataCollector;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * @covers \Miquido\RequestDataCollector\RequestDataCollector
 * @coversDefaultClass \Miquido\RequestDataCollector\RequestDataCollector
 */
class IsRequestExcludedTest extends AbstractRequestDataCollectorTest
{
    use ProphecyTrait;

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
         * @var \Miquido\RequestDataCollector\Filters\Contracts\FilterInterface|\Prophecy\Prophecy\ObjectProphecy $filter1Prophecy
         */
        $filter1Prophecy = $this->prophesize(FilterInterface::class);

        /**
         * @var \Miquido\RequestDataCollector\Filters\Contracts\FilterInterface|\Prophecy\Prophecy\ObjectProphecy $filter2Prophecy
         */
        $filter2Prophecy = $this->prophesize(FilterInterface::class);

        $this->containerProphecy->make($filter1Class, $filter1Options)
            ->shouldBeCalledTimes(3)
            ->willReturn($filter1Prophecy->reveal());

        $this->containerProphecy->make($filter2Class, $filter2Options)
            ->shouldBeCalledTimes(2)
            ->willReturn($filter2Prophecy->reveal());

        /**
         * @var \Illuminate\Http\Request $request1Dummy
         */
        $request1Dummy = $this->prophesize(Request::class)->reveal();

        /**
         * @var \Illuminate\Http\Request $request2Dummy
         */
        $request2Dummy = $this->prophesize(Request::class)->reveal();

        /**
         * @var \Illuminate\Http\Request $request3Dummy
         */
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
}
