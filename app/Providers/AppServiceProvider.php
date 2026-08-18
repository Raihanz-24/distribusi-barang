<?php

namespace App\Providers;

use App\Contracts\RouteOptimizer;
use App\Services\AuditLogger;
use App\Services\Routing\FallbackRouteOptimizer;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(RouteOptimizer::class, FallbackRouteOptimizer::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        date_default_timezone_set(config('app.timezone'));

        Event::listen(Login::class, function (Login $event): void {
            app(AuditLogger::class)->record(
                'auth.login',
                actor: $event->user,
                description: 'Pengguna masuk ke aplikasi.',
            );
        });
    }
}
