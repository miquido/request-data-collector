<?php
declare(strict_types=1);

namespace Miquido\RequestDataCollector\Collectors\Contracts;

use Illuminate\Contracts\Container\Container;

interface ModifiesContainerInterface
{
    public function register(Container $container): void;
}
