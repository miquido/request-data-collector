<?php
declare(strict_types=1);

namespace Miquido\RequestDataCollector\Filters\Contracts;

use Illuminate\Http\Request;

interface FilterInterface
{
    public function accept(Request $request): bool;
}
