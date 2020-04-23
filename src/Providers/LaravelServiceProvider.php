<?php
declare(strict_types=1);

namespace Miquido\RequestDataCollector\Providers;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\ServiceProvider;
use Miquido\RequestDataCollector\Middleware\RequestDataCollectorMiddleware;
use Miquido\RequestDataCollector\RequestDataCollector;

/**
 * @codeCoverageIgnore
 */
class LaravelServiceProvider extends ServiceProvider
{
    private const CONFIG_PATH = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'request-data-collector.php';

    protected $defer = false;

    /**
     * Register services.
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
     */
    public function boot(): void
    {
        $this->publishes([
            self::CONFIG_PATH => $this->getConfigPath() . DIRECTORY_SEPARATOR . 'request-data-collector.php',
        ], 'config');

        $this->registerMiddleware(RequestDataCollectorMiddleware::class);
    }

    protected function getConfigPath(): string
    {
        return $this->app->make('path.config');
    }

    protected function registerMiddleware(string $middleware): void
    {
        /**
         * @var \Illuminate\Contracts\Http\Kernel $kernel
         */
        $kernel = $this->app[Kernel::class];

        $kernel->pushMiddleware($middleware);
    }
}
