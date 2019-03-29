<?php
declare(strict_types=1);

namespace Miquido\RequestDataCollector\Middleware;

use Closure;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Foundation\Application;
use Miquido\RequestDataCollector\RequestDataCollector;

class RequestDataCollectorMiddleware
{
    /**
     * @var \Illuminate\Contracts\Foundation\Application
     */
    private $application;

    /**
     * @var \Miquido\RequestDataCollector\RequestDataCollector
     */
    private $requestDataCollector;

    /**
     * @param \Illuminate\Contracts\Foundation\Application       $application
     * @param \Miquido\RequestDataCollector\RequestDataCollector $requestDataCollector
     */
    public function __construct(Application $application, RequestDataCollector $requestDataCollector)
    {
        $this->application = $application;
        $this->requestDataCollector = $requestDataCollector;
    }

    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure                 $next
     *
     * @throws \Exception
     *
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if (!$this->requestDataCollector->isEnabled() || $this->requestDataCollector->isRequestExcluded($request)) {
            return $next($request);
        }

        try {
            $response = $next($request);
        } catch (\Throwable $throwable) {
            /** @var \Illuminate\Contracts\Debug\ExceptionHandler $handler */
            $handler = $this->application->get(ExceptionHandler::class);

            $handler->report($throwable);

            $response = $handler->render($request, $throwable);
        }

        $response->headers->add([
            'X-Request-Id' => $this->requestDataCollector->getRequestId(),
        ]);

        $this->requestDataCollector->collect($response);

        return $response;
    }
}
