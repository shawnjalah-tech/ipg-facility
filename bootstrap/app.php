<?php

use App\Http\Middleware\CheckAdminRole;
use App\Http\Middleware\CheckStaffRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => CheckAdminRole::class,
            'staff' => CheckStaffRole::class,
        ]);
        $middleware->trustHosts(at: function (): array {
            if (! app()->environment('production')) {
                return [];
            }

            $host = parse_url(config('app.url'), PHP_URL_HOST);

            // Use the configured public host for links generated during requests.
            return [$host ? '^'.preg_quote($host, '/').'$' : '(?!)'];
        }, subdomains: false);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

    })->create();
