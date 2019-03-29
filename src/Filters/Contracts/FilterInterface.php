<?php
declare(strict_types=1);

namespace Miquido\RequestDataCollector\Filters\Contracts;

use Illuminate\Http\Request;

interface FilterInterface
{
    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return bool
     */
    public function accept(Request $request): bool;
}
