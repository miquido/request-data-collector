<?php
declare(strict_types=1);

namespace Miquido\RequestDataCollector\Collectors\Contracts;

interface DataCollectorInterface
{
    /**
     * @return array
     */
    public function collect(): array;
}
