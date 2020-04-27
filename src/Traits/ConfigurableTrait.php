<?php
declare(strict_types=1);

namespace Miquido\RequestDataCollector\Traits;

/**
 * @mixin \Miquido\RequestDataCollector\Collectors\Contracts\ConfigurableInterface
 */
trait ConfigurableTrait
{
    protected $config = [];

    public function setConfig(array $config): void
    {
        $this->config = $config;
    }

    public function getConfig(?string $key = null, $default = null)
    {
        if (null === $key) {
            return $this->config;
        }

        return \array_key_exists($key, $this->config) ?
            $this->config[$key] :
            $default;
    }
}
