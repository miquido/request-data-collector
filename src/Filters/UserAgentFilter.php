<?php
declare(strict_types=1);

namespace Miquido\RequestDataCollector\Filters;

use Illuminate\Http\Request;
use Miquido\RequestDataCollector\Filters\Contracts\FilterInterface;

class UserAgentFilter implements FilterInterface
{
    /**
     * @var array
     */
    private $userAgents = [];

    /**
     * @param array $userAgents
     */
    public function __construct(array $userAgents)
    {
        foreach ($userAgents as $userAgent) {
            $this->userAgents[\mb_strtolower($userAgent)] = true;
        }
    }

    /**
     * @inheritdoc
     */
    public function accept(Request $request): bool
    {
        return isset($this->userAgents[\mb_strtolower($request->server('HTTP_USER_AGENT'))]);
    }
}
