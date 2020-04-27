<?php
declare(strict_types=1);

namespace Miquido\RequestDataCollector\Tests\Filters;

use Illuminate\Http\Request;
use Miquido\RequestDataCollector\Filters\UserAgentFilter;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Miquido\RequestDataCollector\Filters\UserAgentFilter
 * @coversDefaultClass \Miquido\RequestDataCollector\Filters\UserAgentFilter
 */
class UserAgentFilterTest extends TestCase
{
    private const USER_AGENTS = [
        'UserAgent A',
        'UserAgent 2',
        'UserAgent III',
    ];

    /**
     * @var \Miquido\RequestDataCollector\Filters\UserAgentFilter
     */
    private $userAgentFilter;

    protected function setUp(): void
    {
        $this->userAgentFilter = new UserAgentFilter(self::USER_AGENTS);
    }

    /**
     * Provider a set of valid User Agents.
     */
    public function validUserAgentDataProvider(): iterable
    {
        foreach (self::USER_AGENTS as $index => $userAgent) {
            yield \sprintf('User Agent #%d: normal', $index) => [
                'userAgent' => $userAgent,
            ];

            yield \sprintf('User Agent #%d: uppercase', $index) => [
                'userAgent' => \mb_strtoupper($userAgent),
            ];

            yield \sprintf('User Agent #%d: lowercase', $index) => [
                'userAgent' => \mb_strtolower($userAgent),
            ];
        }
    }

    /**
     * Provider a set of invalid User Agents.
     */
    public function invalidUserAgentDataProvider(): iterable
    {
        yield 'Non-existent User Agent' => [
            'userAgent' => 'some random string',
        ];

        foreach (self::USER_AGENTS as $index => $userAgent) {
            yield \sprintf('User Agent #%d: contained in the string at the beginning', $index) => [
                'userAgent' => \sprintf('%s some random string', $userAgent),
            ];

            yield \sprintf('User Agent #%d: contained in the string at the end', $index) => [
                'userAgent' => \sprintf('some random string %s', $userAgent),
            ];
        }
    }

    /**
     * @dataProvider validUserAgentDataProvider
     */
    public function testAcceptPassed(string $userAgent): void
    {
        self::assertTrue($this->userAgentFilter->accept($this->assertGotRequest($userAgent)));
    }

    /**
     * @dataProvider invalidUserAgentDataProvider
     */
    public function testAcceptRejected(string $userAgent): void
    {
        self::assertFalse($this->userAgentFilter->accept($this->assertGotRequest($userAgent)));
    }

    private function assertGotRequest(string $userAgent): Request
    {
        /**
         * @var \Illuminate\Http\Request|\Prophecy\Prophecy\ObjectProphecy $requestProphecy
         */
        $requestProphecy = $this->prophesize(Request::class);

        $requestProphecy->server('HTTP_USER_AGENT')
            ->shouldBeCalledOnce()
            ->willReturn($userAgent);

        /**
         * @var \Illuminate\Http\Request $requestMock
         */
        $requestMock = $requestProphecy->reveal();

        return $requestMock;
    }
}
