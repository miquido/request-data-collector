<?php
declare(strict_types=1);

namespace Miquido\RequestDataCollector\Collectors\Contracts;

interface ConfigurableInterface
{
    /**
     * Allows to set collector's configuration.
     *
     * @param array<string, mixed> $config
     */
    public function setConfig(array $config): void;

    /**
     * Returns array of config if `$key` is `null`.
     * Returns config value if config `$key` exists.
     * Returns `$default` if config `$key` does not exist.
     *
     * @param mixed|null $default
     *
     * @return array<string, mixed>|mixed|null
     */
    public function getConfig(?string $key = null, $default = null);
}
