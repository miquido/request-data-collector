<?php
declare(strict_types=1);

namespace Miquido\RequestDataCollector\Collectors\Contracts;

use Symfony\Component\HttpFoundation\Response;

interface UsesResponseInterface
{
    public function setResponse(Response $response): void;
}
