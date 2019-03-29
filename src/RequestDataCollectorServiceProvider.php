<?php
declare(strict_types=1);

namespace Miquido\RequestDataCollector;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\ServiceProvider;
use Miquido\RequestDataCollector\Middleware\RequestDataCollectorMiddleware;

/**
 * @codeCoverageIgnore
 */
class RequestDataCollectorServiceProvider extends ServiceProvider
{
    private const CONFIG_PATH = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'request-data-collector.php';

    protected $defer = false;

    /**
     * Register services.
     *
     * @return void
     */
    public function register(): void
    {
        $this->mergeConfigFrom(self::CONFIG_PATH, 'request-data-collector');

        $this->app->when(RequestDataCollector::class)
            ->needs('$config')
            ->give($this->app->get('config')->get('request-data-collector'));

        $this->app->singleton(RequestDataCollector::class);
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot(): void
    {
        $this->publishes([self::CONFIG_PATH => $this->app->make('path.config') . DIRECTORY_SEPARATOR . 'request-data-collector.php'], 'config');

        /** @var \Illuminate\Contracts\Http\Kernel $kernel */
        $kernel = $this->app[Kernel::class];

        $kernel->pushMiddleware(RequestDataCollectorMiddleware::class);
    }
}
