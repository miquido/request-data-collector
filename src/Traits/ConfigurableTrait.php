<?php
declare(strict_types=1);

namespace Miquido\RequestDataCollector\Traits;

/**
 * @mixin \Miquido\RequestDataCollector\Collectors\Contracts\ConfigurableInterface
 */
trait ConfigurableTrait
{
    protected $config = [];

    /**
     * @inheritDoc
     */
    public function setConfig(array $config): void
    {
        $this->config = $config;
    }

    /**
     * @inheritDoc
     */
    public function getConfig(?string $key = null, $default = null)
    {
        return null === $key ?
            $this->config :
            $this->config[$key] ?? $default;
    }
}
