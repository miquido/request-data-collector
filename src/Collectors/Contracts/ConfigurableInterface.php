<?php
declare(strict_types=1);

namespace Miquido\RequestDataCollector\Collectors\Contracts;

interface ConfigurableInterface
{
    /**
     * @param array $config
     *
     * @return mixed
     */
    public function setConfig(array $config);
}
