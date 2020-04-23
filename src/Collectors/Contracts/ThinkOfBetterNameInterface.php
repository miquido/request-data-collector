<?php
declare(strict_types=1);

namespace Miquido\RequestDataCollector\Collectors\Contracts;

interface ThinkOfBetterNameInterface
{
    /**
     * @param array<mixed> $collected
     *
     * @return iterable<string, array>
     *
     * @see \Miquido\RequestDataCollector\Collectors\Contracts\DataCollectorInterface::collect()
     */
    public function getThinkOfBetterName(array $collected): iterable;
}
