<?php
declare(strict_types=1);

namespace Miquido\RequestDataCollector\Tests\RequestDataCollector;

use Miquido\RequestDataCollector\RequestDataCollector;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * @covers \Miquido\RequestDataCollector\RequestDataCollector
 * @coversDefaultClass \Miquido\RequestDataCollector\RequestDataCollector
 */
class AGetStartedAtTest extends AbstractRequestDataCollectorTest
{
    use ProphecyTrait;

    public function testGetStartedAt(): void
    {
        self::assertSame(-1.0, RequestDataCollector::getStartedAt());

        $startedAt = \microtime(true);

        new RequestDataCollector(
            $this->containerMock,
            $this->logManagerMock,
            $this->requestMock,
            []
        );

        self::assertEqualsWithDelta($startedAt, RequestDataCollector::getStartedAt(), 0.001);
    }
}
