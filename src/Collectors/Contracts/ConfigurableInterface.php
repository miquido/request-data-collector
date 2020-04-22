<?php
declare(strict_types=1);

namespace Miquido\RequestDataCollector\Collectors\Contracts;

interface ConfigurableInterface
{
    /**
     * @param array<string, mixed> $config
     */
    public function setConfig(array $config): void;

    /**
     * Returns array of config if `$key` is `null`. Returns config value otherwise.
     * Can return `null` if config does not exist or if it is its value.
     *
     * @param mixed|null $default
     *
     * @return array<string, mixed>|mixed|null
     */
    public function getConfig(?string $key = null, $default = null);
}
