<?php
declare(strict_types=1);

namespace Miquido\RequestDataCollector\Filters;

use Illuminate\Http\Request;
use Miquido\RequestDataCollector\Filters\Contracts\FilterInterface;

class UserAgentFilter implements FilterInterface
{
    /**
     * @var string[]
     */
    private $userAgents = [];

    /**
     * @param string[] $userAgents
     */
    public function __construct(array $userAgents)
    {
        foreach ($userAgents as $userAgent) {
            $this->userAgents[\mb_strtolower($userAgent)] = true;
        }
    }

    public function accept(Request $request): bool
    {
        return isset($this->userAgents[\mb_strtolower($request->server('HTTP_USER_AGENT'))]);
    }
}
