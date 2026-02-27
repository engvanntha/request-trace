<?php

namespace Engvanntha\RequestTrace;

use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Support\ServiceProvider;
use Engvanntha\RequestTrace\Middleware\CaptureRequestTrace;

class RequestTraceServiceProvider extends ServiceProvider
{
    protected static $middlewarePushed = false;

    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/requesttrace.php', 'requesttrace');
    }

    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes(
                array(__DIR__.'/../config/requesttrace.php' => config_path('requesttrace.php')),
                'requesttrace-config'
            );
        }

        if (config('requesttrace.auto_register_middleware', false)) {
            $this->pushContextMiddleware();
        }
    }

    protected function pushContextMiddleware()
    {
        if (static::$middlewarePushed) {
            return;
        }

        $this->app->resolving(HttpKernel::class, function ($kernel) {
            if (static::$middlewarePushed) {
                return;
            }

            if (is_object($kernel) && method_exists($kernel, 'pushMiddleware')) {
                $kernel->pushMiddleware(CaptureRequestTrace::class);
                static::$middlewarePushed = true;
            }
        });
    }
}
