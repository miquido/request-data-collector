<?php
declare(strict_types=1);

namespace Miquido\RequestDataCollector\Collectors\Contracts;

interface ConfigurableInterface
{
    /**
     * @param array<string, mixed> $config
     *
     * @return mixed
     */
    public function setConfig(array $config);
}
