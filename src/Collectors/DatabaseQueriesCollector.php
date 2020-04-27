<?php
declare(strict_types=1);

namespace Miquido\RequestDataCollector\Collectors;

use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Events\QueryExecuted;
use Miquido\RequestDataCollector\Collectors\Contracts\ConfigurableInterface;
use Miquido\RequestDataCollector\Collectors\Contracts\DataCollectorInterface;
use Miquido\RequestDataCollector\Collectors\Contracts\SupportsSeparateLogEntriesInterface;
use Miquido\RequestDataCollector\Traits\ConfigurableTrait;

class DatabaseQueriesCollector implements DataCollectorInterface, ConfigurableInterface, SupportsSeparateLogEntriesInterface
{
    use ConfigurableTrait {
        ConfigurableTrait::setConfig as setConfigTrait;
    }

    /**
     * @var \Illuminate\Database\DatabaseManager
     */
    private $databaseManager;

    /**
     * @var array[]
     */
    private $queryLog = [];

    public function __construct(DatabaseManager $databaseManager)
    {
        $this->databaseManager = $databaseManager;
    }

    public function setConfig(array $config): void
    {
        $this->setConfigTrait($config);

        foreach ($config['connections'] ?? [] as $connectionName) {
            $connection = $this->databaseManager->connection($connectionName);
            $connectionName = $this->getConnectionName($connection->getName());

            if (isset($this->queryLog[$connectionName])) {
                continue;
            }

            $this->queryLog[$connectionName] = [
                'queries'                => [],
                'queries_count'          => 0,
                'distinct_queries_count' => 0,
                'distinct_queries_ratio' => 0,
            ];

            $connection->listen(function (QueryExecuted $event): void {
                $connectionName = $this->getConnectionName($event->connectionName);

                ++$this->queryLog[$connectionName]['queries_count'];

                $this->queryLog[$connectionName]['queries'][] = [
                    'query'    => $event->sql,
                    'bindings' => $event->bindings,
                    'time'     => \round($event->time / 1000.0, 5),
                ];
            });
        }
    }

    public function collect(): array
    {
        foreach ($this->queryLog as $index => $entry) {
            $this->queryLog[$index]['distinct_queries_count'] = \count(\array_unique(\array_column($entry['queries'], 'query')));
            $this->queryLog[$index]['distinct_queries_ratio'] = (0 === $entry['queries_count']) ?
                0.0 :
                (float) ($this->queryLog[$index]['distinct_queries_count'] / $entry['queries_count']);
        }

        return $this->queryLog;
    }

    public function getSeparateLogEntries(array $collected): iterable
    {
        foreach ($collected as $connectionName => $statistics) {
            foreach ($statistics['queries'] as $index => $query) {
                yield \sprintf('%s.query.%d', $connectionName, $index) => $query;
            }

            yield \sprintf('%s.stats', $connectionName) => [
                'queries_count'          => $statistics['queries_count'],
                'distinct_queries_count' => $statistics['distinct_queries_count'],
                'distinct_queries_ratio' => $statistics['distinct_queries_ratio'],
            ];
        }
    }

    private function getConnectionName(?string $name): string
    {
        return $name ?? '_default';
    }
}
