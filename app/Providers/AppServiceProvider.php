<?php

namespace App\Providers;

use App\Support\LoadTestSandbox;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFour();
        RateLimiter::for('login', function (Request $request) {
            $email = $request->input('email');

            return LoadTestSandbox::active()
                ? [Limit::perMinute(1000)->by('test-ip|'.$request->ip())]
                : [
                    Limit::perMinute(5)->by((is_string($email) ? strtolower($email) : '').'|'.$request->ip()),
                    Limit::perMinute(30)->by($request->ip()),
                ];
        });
    }
}
