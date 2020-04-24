<?php
declare(strict_types=1);

namespace Miquido\RequestDataCollector\Tests\RequestDataCollector;

use Miquido\RequestDataCollector\RequestDataCollector;

/**
 * @covers \Miquido\RequestDataCollector\RequestDataCollector
 * @coversDefaultClass \Miquido\RequestDataCollector\RequestDataCollector
 */
class GetAndSetRequestIdTest extends AbstractRequestDataCollectorTest
{
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
}
