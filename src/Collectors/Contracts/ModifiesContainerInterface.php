<?php
declare(strict_types=1);

namespace Miquido\RequestDataCollector\Collectors\Contracts;

use Illuminate\Contracts\Container\Container;

interface ModifiesContainerInterface
{
    /**
     * @param \Illuminate\Contracts\Container\Container $container
     */
    public function register(Container $container): void;
}
