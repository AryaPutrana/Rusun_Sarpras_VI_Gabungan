<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to the "home" route for your application.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/dashboard';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }

    /**
     * Configure the rate limiters for the application.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // Login throttle: 5 attempts per minute per email+IP combination
        // Prevents brute force attacks while allowing legitimate retries
        RateLimiter::for('login', function (Request $request) {
            $email = $request->input('email', '');
            return Limit::perMinute(5)->by($email.'|'.$request->ip());
        });

        // Export throttle: Different limits for admin vs petugas
        // Admin: 50 exports/min, Petugas: 10 exports/min, Guest: 3/min
        RateLimiter::for('exports', function (Request $request) {
            $user = $request->user();
            if (!$user) {
                return Limit::perMinute(3)->by($request->ip());
            }
            $limit = $user->role === 'admin' ? 50 : 10;
            return Limit::perMinute($limit)->by($user->id);
        });
    }
}
