<?php
declare(strict_types=1);

namespace Miquido\RequestDataCollector\Collectors\Contracts;

interface SupportsSeparateLogEntriesInterface extends ConfigurableInterface
{
    /**
     * @param array<mixed> $collected
     *
     * @return iterable<string, array>
     *
     * @see \Miquido\RequestDataCollector\Collectors\Contracts\DataCollectorInterface::collect()
     */
    public function getSeparateLogEntries(array $collected): iterable;
}
