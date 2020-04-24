<?php
declare(strict_types=1);

namespace Miquido\RequestDataCollector\Tests\RequestDataCollector;

use Miquido\RequestDataCollector\RequestDataCollector;

/**
 * @covers \Miquido\RequestDataCollector\RequestDataCollector
 * @coversDefaultClass \Miquido\RequestDataCollector\RequestDataCollector
 */
class IsEnabledTest extends AbstractRequestDataCollectorTest
{
    /**
     * Provides a set of valid values for 'enabled' option.
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

    /**
     * @dataProvider validEnabledOptionDataProvider
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
}
