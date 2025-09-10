<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to your application's "home" route.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/home';

    public function boot(): void
    {
        $this->configureRateLimiting();
        
        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->name('api.') // This will prefix all route names with 'api.'
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }

    protected function configureRateLimiting(): void
    {
        // API Rate Limiting
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // Auth Rate Limiting (Login/Register)
        RateLimiter::for('auth', function (Request $request) {
            return [
                Limit::perMinute(5)->by($request->ip()),
                Limit::perDay(20)->by($request->ip())
            ];
        });

        // Public API Rate Limiting (for streaming endpoints)
        RateLimiter::for('public', function (Request $request) {
            return Limit::perMinute(100)->by($request->ip());
        });

        // Admin API Rate Limiting
        RateLimiter::for('admin', function (Request $request) {
            return $request->user()?->role === 'admin'
                ? Limit::perMinute(120)->by($request->user()->id)
                : Limit::perMinute(30)->by($request->ip());
        });

        // Download Rate Limiting
        RateLimiter::for('download', function (Request $request) {
            return [
                Limit::perMinute(10)->by($request->user()?->id ?: $request->ip()),
                Limit::perHour(50)->by($request->user()?->id ?: $request->ip())
            ];
        });

        // Streaming Rate Limiting (for video/thumbnail streaming)
        RateLimiter::for('streaming', function (Request $request) {
            return [
                Limit::perMinute(200)->by($request->ip()), // High limit for streaming
                Limit::perHour(1000)->by($request->ip())   // Daily streaming limit
            ];
        });
    }
}