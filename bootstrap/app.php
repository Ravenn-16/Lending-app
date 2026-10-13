<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Enable only behind Render's ingress, which terminates HTTPS.
        if (env('TRUST_RENDER_PROXY', false)) {
            $middleware->trustProxies(at: '*', headers:
                \Illuminate\Http\Request::HEADER_X_FORWARDED_FOR |
                \Illuminate\Http\Request::HEADER_X_FORWARDED_PROTO
            );
        }
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->dontFlash(['code']);
    })->create();
