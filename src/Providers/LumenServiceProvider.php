<?php
declare(strict_types=1);

namespace Miquido\RequestDataCollector\Providers;

/**
 * @codeCoverageIgnore
 */
class LumenServiceProvider extends LaravelServiceProvider
{
    protected function getConfigPath(): string
    {
        return $this->app->basePath() . DIRECTORY_SEPARATOR . 'config';
    }

    protected function registerMiddleware(string $middleware): void
    {
        $this->app->middleware([$middleware]);
    }
}
