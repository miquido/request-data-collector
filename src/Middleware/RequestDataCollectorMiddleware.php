<?php
declare(strict_types=1);

namespace Miquido\RequestDataCollector\Middleware;

use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Miquido\RequestDataCollector\RequestDataCollector;

class RequestDataCollectorMiddleware
{
    /**
     * @var \Illuminate\Contracts\Container\Container
     */
    private $application;

    /**
     * @var \Miquido\RequestDataCollector\RequestDataCollector
     */
    private $requestDataCollector;

    public function __construct(Container $application, RequestDataCollector $requestDataCollector)
    {
        $this->application = $application;
        $this->requestDataCollector = $requestDataCollector;
    }

    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @throws \Exception
     *
     * @return mixed
     */
    public function handle($request, callable $next)
    {
        if (!$this->requestDataCollector->isEnabled() || $this->requestDataCollector->isRequestExcluded($request)) {
            return $next($request);
        }

        try {
            $response = $next($request);
        } catch (\Throwable $throwable) {
            /**
             * @var \Illuminate\Contracts\Debug\ExceptionHandler $handler
             */
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
