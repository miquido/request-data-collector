<?php
declare(strict_types=1);

namespace Miquido\RequestDataCollector\Tests\RequestDataCollector;

use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Log\LogManager;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;

abstract class AbstractRequestDataCollectorTest extends TestCase
{
    /**
     * @var \Illuminate\Contracts\Container\Container|\Prophecy\Prophecy\ObjectProphecy
     */
    protected $containerProphecy;

    /**
     * @var \Illuminate\Log\LogManager|\Prophecy\Prophecy\ObjectProphecy
     */
    protected $logManagerProphecy;

    /**
     * @var \Illuminate\Http\Request|\Prophecy\Prophecy\ObjectProphecy
     */
    protected $requestProphecy;

    /**
     * @var \Illuminate\Contracts\Foundation\Application
     */
    protected $containerMock;

    /**
     * @var \Illuminate\Log\LogManager
     */
    protected $logManagerMock;

    /**
     * @var \Illuminate\Http\Request
     */
    protected $requestMock;

    protected function setUp(): void
    {
        $this->containerProphecy = $this->prophesize(Container::class);
        $this->logManagerProphecy = $this->prophesize(LogManager::class);
        $this->requestProphecy = $this->prophesize(Request::class);

        $this->containerMock = $this->containerProphecy->reveal();
        $this->logManagerMock = $this->logManagerProphecy->reveal();
        $this->requestMock = $this->requestProphecy->reveal();
    }

    protected function assertXRequestIdHeaderIsNotChecked(): void
    {
        $this->requestProphecy->header('x-request-id', Argument::any())
            ->shouldNotBeCalled();
    }
}
