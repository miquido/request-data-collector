<?php
declare(strict_types=1);

namespace Miquido\RequestDataCollector\Traits;

trait ConfigurableTrait
{
    protected $config = [];

    /**
     * @param array $config
     */
    public function setConfig(array $config): void
    {
        $this->config = $config;
    }
}
