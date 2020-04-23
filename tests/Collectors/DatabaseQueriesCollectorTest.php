<?php
declare(strict_types=1);

namespace Miquido\RequestDataCollector\Tests\Collectors;

use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Events\QueryExecuted;
use Miquido\RequestDataCollector\Collectors\DatabaseQueriesCollector;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;

/**
 * @covers \Miquido\RequestDataCollector\Collectors\DatabaseQueriesCollector
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
         */
        $defaultConnectionMock = $defaultConnectionProphecy->reveal();

        /**
         * @var \Illuminate\Database\Connection $fooConnectionMock
         */
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

    /**
     * @depends testCollectWithEmptyListOfConnections
     * @depends testCollectWithDefaultConnectionNameSet
     * @depends testCollectWithMultipleConnectionNamesSet
     *
     * @covers ::getSeparateLogEntries
     */
    public function testGetSeparateLogEntriesForEachConnection(): void
    {
        $defaultConnectionProphecy = $this->assertQueriesLoggingWasEnabledForConnection(null);
        $fooConnectionProphecy = $this->assertQueriesLoggingWasEnabledForConnection('foo');
        $this->assertQueriesLoggingWasEnabledForConnection('bar');

        /**
         * @var \Illuminate\Database\Connection $defaultConnectionMock
         */
        $defaultConnectionMock = $defaultConnectionProphecy->reveal();

        /**
         * @var \Illuminate\Database\Connection $fooConnectionMock
         */
        $fooConnectionMock = $fooConnectionProphecy->reveal();

        $queryExecutedEvent1 = new QueryExecuted('SOME SQL 1;', [1, 2, 3], 12.34, $defaultConnectionMock);
        $queryExecutedEvent2 = new QueryExecuted('SOME SQL 2;', [4, 5, 6], 5678.0, $fooConnectionMock);
        $queryExecutedEvent3 = new QueryExecuted('SOME SQL 2;', [7, 8, 9], 9.0, $fooConnectionMock);

        $this->assertQueryExecutedEventWasFired($defaultConnectionProphecy, $queryExecutedEvent1);
        $this->assertQueryExecutedEventWasFired($fooConnectionProphecy, $queryExecutedEvent2, $queryExecutedEvent3);

        $this->databaseQueriesCollector->setConfig([
            'connections' => [
                null,
                'foo',
                'bar',
            ],
        ]);

        $separateLogEntries = [];

        foreach ($this->databaseQueriesCollector->getSeparateLogEntries($this->databaseQueriesCollector->collect()) as $key => $logEntry) {
            $separateLogEntries[$key] = $logEntry;
        }

        // Check if no log entries overlapped
        self::assertCount(12, $separateLogEntries);

        self::assertSeparateCollectorQueriesWereRan($separateLogEntries, '_default', 1, [$queryExecutedEvent1]);
        self::assertSeparateCollectorQueriesWereRan($separateLogEntries, 'foo', 1, [$queryExecutedEvent2, $queryExecutedEvent3]);
        self::assertSeparateCollectorNoQueriesWereRan($separateLogEntries, 'bar');
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
     */
    private function assertQueryExecutedEventWasFired(ObjectProphecy $connectionProphecy, QueryExecuted ...$queryExecutedEvents): void
    {
        $connectionProphecy->listen(Argument::type('callable'))
            ->will(function (array $args) use (&$queryExecutedEvents) {
                /**
                 * @var callable $callback
                 */
                [$callback] = $args;

                foreach ($queryExecutedEvents as $queryExecutedEvent) {
                    $callback($queryExecutedEvent);
                }

                return true;
            });

        $connectionProphecy->getName()
            ->shouldBeCalledTimes(1 + \count($queryExecutedEvents));
    }

    private static function assertSeparateCollectorNoQueriesWereRan(array $logEntires, string $connection): void
    {
        self::assertArrayHasKey(\sprintf('%s.queries_count', $connection), $logEntires);
        self::assertSame(0, $logEntires[\sprintf('%s.queries_count', $connection)]);

        self::assertArrayHasKey(\sprintf('%s.distinct_queries_count', $connection), $logEntires);
        self::assertSame(0, $logEntires[\sprintf('%s.distinct_queries_count', $connection)]);

        self::assertArrayHasKey(\sprintf('%s.distinct_queries_ratio', $connection), $logEntires);
        self::assertSame(0.0, $logEntires[\sprintf('%s.distinct_queries_ratio', $connection)]);
    }

    /**
     * @param \Illuminate\Database\Events\QueryExecuted[] $queries
     */
    private static function assertSeparateCollectorQueriesWereRan(array $logEntires, string $connection, int $numberOfDistinctQueries, array $queries): void
    {
        $numberOfQueries = \count($queries);

        self::assertArrayHasKey(\sprintf('%s.queries_count', $connection), $logEntires);
        self::assertSame($numberOfQueries, $logEntires[\sprintf('%s.queries_count', $connection)]);

        self::assertArrayHasKey(\sprintf('%s.distinct_queries_count', $connection), $logEntires);
        self::assertSame($numberOfDistinctQueries, $logEntires[\sprintf('%s.distinct_queries_count', $connection)]);

        self::assertArrayHasKey(\sprintf('%s.distinct_queries_ratio', $connection), $logEntires);
        self::assertSame($numberOfDistinctQueries / (float) $numberOfQueries, $logEntires[\sprintf('%s.distinct_queries_ratio', $connection)]);

        foreach (\array_values($queries) as $index => $query) {
            $key = \sprintf('%s.query.%d', $connection, $index);

            self::assertArrayHasKey($key, $logEntires);
            self::assertSame($query->sql, $logEntires[$key]['query']);
            self::assertSame($query->bindings, $logEntires[$key]['bindings']);
            self::assertSame($query->time / 1000.0, $logEntires[$key]['time']);
        }
    }
}
