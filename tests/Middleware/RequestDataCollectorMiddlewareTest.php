<?php
declare(strict_types=1);

namespace Miquido\RequestDataCollector\Tests\Middleware;

use Exception;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Miquido\RequestDataCollector\Middleware\RequestDataCollectorMiddleware;
use Miquido\RequestDataCollector\RequestDataCollector;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

/**
 * @covers \Miquido\RequestDataCollector\Middleware\RequestDataCollectorMiddleware
 * @coversDefaultClass \Miquido\RequestDataCollector\Middleware\RequestDataCollectorMiddleware
 */
class RequestDataCollectorMiddlewareTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @var \Illuminate\Contracts\Container\Container|\Prophecy\Prophecy\ObjectProphecy
     */
    private $containerProphecy;

    /**
     * @var \Miquido\RequestDataCollector\RequestDataCollector|\Prophecy\Prophecy\ObjectProphecy
     */
    private $requestDataCollectorProphecy;

    /**
     * @var \Illuminate\Http\Request
     */
    private $requestDummy;

    /**
     * @var \Illuminate\Http\Response
     */
    private $responseDummy;

    /**
     * @var \Miquido\RequestDataCollector\Middleware\RequestDataCollectorMiddleware
     */
    private $requestDataCollectorMiddleware;

    protected function setUp(): void
    {
        $this->containerProphecy = $this->prophesize(Container::class);
        $this->requestDataCollectorProphecy = $this->prophesize(RequestDataCollector::class);
        $this->requestDummy = $this->prophesize(Request::class)->reveal();
        $this->responseDummy = $this->prophesize(Response::class)->reveal();

        /**
         * @var \Illuminate\Contracts\Container\Container $containerMock
         */
        $containerMock = $this->containerProphecy->reveal();

        /**
         * @var \Miquido\RequestDataCollector\RequestDataCollector $requestDataCollectorMock
         */
        $requestDataCollectorMock = $this->requestDataCollectorProphecy->reveal();

        $this->requestDataCollectorMiddleware = new RequestDataCollectorMiddleware($containerMock, $requestDataCollectorMock);
    }

    public function testHandleWhenRequestDataCollectorIsDisabled(): void
    {
        $this->requestDataCollectorProphecy->isEnabled()
            ->shouldBeCalledOnce()
            ->willReturn(false);

        self::assertSame(
            $this->responseDummy,
            $this->requestDataCollectorMiddleware->handle($this->requestDummy, function ($request) {
                self::assertSame($this->requestDummy, $request);

                return $this->responseDummy;
            })
        );
    }

    public function testHandleExcludedRequest(): void
    {
        $this->assertRequestDataCollectorIsEnabled();

        $this->requestDataCollectorProphecy->isRequestExcluded($this->requestDummy)
            ->shouldBeCalledOnce()
            ->willReturn(true);

        self::assertSame(
            $this->responseDummy,
            $this->requestDataCollectorMiddleware->handle($this->requestDummy, function ($request) {
                self::assertSame($this->requestDummy, $request);

                return $this->responseDummy;
            })
        );
    }

    public function testHandleSuccessfulRequest(): void
    {
        $requestId = \uniqid('request-data-collector.', true);

        $this->assertRequestDataCollectorIsEnabled();
        $this->assertRequestIsNotExcluded();

        $this->requestDataCollectorProphecy->getRequestId()
            ->shouldBeCalledOnce()
            ->willReturn($requestId);

        $responseHeaderBagProphecy = $this->assertResponseHasHeaderBag();

        $responseHeaderBagProphecy->add([
            'X-Request-Id' => $requestId,
        ]);

        $this->requestDataCollectorProphecy->collect($this->responseDummy)
            ->shouldBeCalledOnce();

        self::assertSame(
            $this->responseDummy,
            $this->requestDataCollectorMiddleware->handle($this->requestDummy, function ($request) {
                self::assertSame($this->requestDummy, $request);

                return $this->responseDummy;
            })
        );
    }

    public function testHandleFailedRequest(): void
    {
        $requestId = \uniqid('request-data-collector.', true);
        $exception = new Exception();

        $this->assertRequestDataCollectorIsEnabled();
        $this->assertRequestIsNotExcluded();

        /**
         * @var \Illuminate\Contracts\Debug\ExceptionHandler|\Prophecy\Prophecy\ObjectProphecy $exceptionHandlerProphecy
         */
        $exceptionHandlerProphecy = $this->prophesize(ExceptionHandler::class);

        $this->containerProphecy->get(ExceptionHandler::class)
            ->shouldBeCalledOnce()
            ->willReturn($exceptionHandlerProphecy->reveal());

        $exceptionHandlerProphecy->report($exception)
            ->shouldBeCalledOnce();

        $exceptionHandlerProphecy->render($this->requestDummy, $exception)
            ->shouldBeCalledOnce()
            ->willReturn($this->responseDummy);

        $this->requestDataCollectorProphecy->getRequestId()
            ->shouldBeCalledOnce()
            ->willReturn($requestId);

        $responseHeaderBagProphecy = $this->assertResponseHasHeaderBag();

        $responseHeaderBagProphecy->add([
            'X-Request-Id' => $requestId,
        ]);

        $this->requestDataCollectorProphecy->collect($this->responseDummy)
            ->shouldBeCalledOnce();

        self::assertSame(
            $this->responseDummy,
            $this->requestDataCollectorMiddleware->handle($this->requestDummy, function ($request) use ($exception): void {
                self::assertSame($this->requestDummy, $request);

                throw $exception;
            })
        );
    }

    private function assertRequestDataCollectorIsEnabled(): void
    {
        $this->requestDataCollectorProphecy->isEnabled()
            ->shouldBeCalledOnce()
            ->willReturn(true);
    }

    private function assertRequestIsNotExcluded(): void
    {
        $this->requestDataCollectorProphecy->isRequestExcluded($this->requestDummy)
            ->shouldBeCalledOnce()
            ->willReturn(false);
    }

    /**
     * @return \Prophecy\Prophecy\ObjectProphecy|\Symfony\Component\HttpFoundation\ResponseHeaderBag
     */
    private function assertResponseHasHeaderBag(): ObjectProphecy
    {
        $responseHeaderBagProphecy = $this->prophesize(ResponseHeaderBag::class);

        $this->responseDummy->headers = $responseHeaderBagProphecy->reveal();

        return $responseHeaderBagProphecy;
    }
}
