<?php
declare(strict_types=1);

namespace Tests\Collectors;

use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Events\QueryExecuted;
use Miquido\RequestDataCollector\Collectors\DatabaseQueriesCollector;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;

/**
 * @coversDefaultClass \Miquido\RequestDataCollector\Collectors\DatabaseQueriesCollector
 */
class DatabaseQueriesCollectorTest extends TestCase
{
    /**
     * @var \Illuminate\Database\DatabaseManager&\Prophecy\Prophecy\ObjectProphecy
     */
    private $databaseManagerProphecy;

    /**
     * @var \Miquido\RequestDataCollector\Collectors\DatabaseQueriesCollector
     */
    private $databaseQueriesCollector;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        $this->databaseManagerProphecy = $this->prophesize(DatabaseManager::class);

        /**
         * @var \Illuminate\Database\DatabaseManager $databaseManagerMock
         */
        $databaseManagerMock = $this->databaseManagerProphecy->reveal();

        $this->databaseQueriesCollector = new DatabaseQueriesCollector($databaseManagerMock);
    }

    public function testCollectWithEmptyListOfConnections(): void
    {
        self::assertEquals([], $this->databaseQueriesCollector->collect());
    }

    public function testSetConnectionsToCollectQueriesFrom(): void
    {
        $this->assertQueriesLoggingWasEnabledForConnection('foo');
        $this->assertQueriesLoggingWasEnabledForConnection('bar');

        $this->databaseQueriesCollector->setConfig([
            'connections' => [
                'foo',
                'bar',
            ],
        ]);
    }

    public function testCollectWithDefaultConnectionNameSet(): void
    {
        $defaultConnectionProphecy = $this->assertQueriesLoggingWasEnabledForConnection(null);

        /**
         * @var \Illuminate\Database\Connection $defaultConnectionMock
         */
        $defaultConnectionMock = $defaultConnectionProphecy->reveal();

        $queryExecutedEvent = new QueryExecuted('SOME SQL;', [1, 2], 12.3, $defaultConnectionMock);

        $this->assertQueryExecutedEventWasFired($defaultConnectionProphecy, $queryExecutedEvent);

        $this->databaseQueriesCollector->setConfig([
            'connections' => [
                null,
            ],
        ]);

        self::assertEquals([
            '_default' => [
                'queries' => [
                    [
                        'query'    => $queryExecutedEvent->sql,
                        'bindings' => $queryExecutedEvent->bindings,
                        'time'     => \round($queryExecutedEvent->time / 1000.0, 5),
                    ],
                ],

                'queries_count'          => 1,
                'distinct_queries_count' => 1,
                'distinct_queries_ratio' => 1.0,
            ],
        ], $this->databaseQueriesCollector->collect());
    }

    public function testCollectWithMultipleConnectionNamesSet(): void
    {
        $defaultConnectionProphecy = $this->assertQueriesLoggingWasEnabledForConnection(null);
        $fooConnectionProphecy = $this->assertQueriesLoggingWasEnabledForConnection('foo');
        $this->assertQueriesLoggingWasEnabledForConnection('bar');

        /**
         * @var \Illuminate\Database\Connection $defaultConnectionMock
         * @var \Illuminate\Database\Connection $fooConnectionMock
         */
        $defaultConnectionMock = $defaultConnectionProphecy->reveal();
        $fooConnectionMock = $fooConnectionProphecy->reveal();

        $queryExecutedEvent1 = new QueryExecuted('SOME SQL 1;', [1, 2, 3], 12.34, $defaultConnectionMock);
        $queryExecutedEvent2 = new QueryExecuted('SOME SQL 2;', [4, 5, 6], 5678.0, $fooConnectionMock);

        $this->assertQueryExecutedEventWasFired($defaultConnectionProphecy, $queryExecutedEvent1);
        $this->assertQueryExecutedEventWasFired($fooConnectionProphecy, $queryExecutedEvent2);

        $this->databaseQueriesCollector->setConfig([
            'connections' => [
                null,
                'foo',
                'bar',
            ],
        ]);

        self::assertEquals([
            '_default' => [
                'queries' => [
                    [
                        'query'    => $queryExecutedEvent1->sql,
                        'bindings' => $queryExecutedEvent1->bindings,
                        'time'     => \round($queryExecutedEvent1->time / 1000.0, 5),
                    ],
                ],

                'queries_count'          => 1,
                'distinct_queries_count' => 1,
                'distinct_queries_ratio' => 1.0,
            ],

            'foo' => [
                'queries' => [
                    [
                        'query'    => $queryExecutedEvent2->sql,
                        'bindings' => $queryExecutedEvent2->bindings,
                        'time'     => \round($queryExecutedEvent2->time / 1000.0, 5),
                    ],
                ],

                'queries_count'          => 1,
                'distinct_queries_count' => 1,
                'distinct_queries_ratio' => 1.0,
            ],

            'bar' => [
                'queries'                => [],
                'queries_count'          => 0,
                'distinct_queries_count' => 0,
                'distinct_queries_ratio' => 0.0,
            ],
        ], $this->databaseQueriesCollector->collect());
    }

    public function testSkipRegisteringEventListenerForSameConnection(): void
    {
        $defaultConnectionProphecy = $this->assertQueriesLoggingWasEnabledForConnection(null);
        $this->assertQueriesLoggingWasEnabledForConnection('foo');

        $this->databaseManagerProphecy->connection(null)
            ->shouldBeCalledTimes(2);

        $defaultConnectionProphecy->getName()
            ->shouldBeCalledTimes(2);

        $this->databaseQueriesCollector->setConfig([
            'connections' => [
                null,
            ],
        ]);

        $this->databaseQueriesCollector->setConfig([
            'connections' => [
                null,
                'foo',
            ],
        ]);

        self::assertEquals([
            '_default' => [
                'queries'                => [],
                'queries_count'          => 0,
                'distinct_queries_count' => 0,
                'distinct_queries_ratio' => 0.0,
            ],

            'foo' => [
                'queries'                => [],
                'queries_count'          => 0,
                'distinct_queries_count' => 0,
                'distinct_queries_ratio' => 0.0,
            ],
        ], $this->databaseQueriesCollector->collect());
    }

    /**
     * @param string|null $connectionName
     *
     * @return \Illuminate\Database\Connection&\Prophecy\Prophecy\ObjectProphecy
     */
    private function assertQueriesLoggingWasEnabledForConnection(?string $connectionName): object
    {
        /**
         * @var \Illuminate\Database\Connection&\Prophecy\Prophecy\ObjectProphecy $connectionProphecy
         */
        $connectionProphecy = $this->prophesize(Connection::class);

        $this->databaseManagerProphecy->connection($connectionName)
            ->shouldBeCalledOnce()
            ->willReturn($connectionProphecy->reveal());

        $connectionProphecy->listen(Argument::type('callable'))
            ->shouldBeCalledOnce();

        $connectionProphecy->getName()
            ->shouldBeCalledOnce()
            ->willReturn($connectionName);

        return $connectionProphecy;
    }

    /**
     * @param \Illuminate\Database\Connection&\Prophecy\Prophecy\ObjectProphecy $connectionProphecy
     * @param \Illuminate\Database\Events\QueryExecuted                         $queryExecutedEvent
     */
    private function assertQueryExecutedEventWasFired(ObjectProphecy $connectionProphecy, QueryExecuted $queryExecutedEvent): void
    {
        $connectionProphecy->listen(Argument::type('callable'))
            ->will(function (array $args) use ($queryExecutedEvent) {
                /**
                 * @var callable $callback
                 */
                [$callback] = $args;

                return $callback($queryExecutedEvent);
            });

        $connectionProphecy->getName()
            ->shouldBeCalledTimes(2);
    }
}
