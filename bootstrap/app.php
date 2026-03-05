<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\EnsureTokenIsValid;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->append(EnsureTokenIsValid::class);
        
        // Register permission middleware
        $middleware->alias([
            'permission' => \App\Http\Middleware\CheckPermission::class,
            'filter.customer' => \App\Http\Middleware\FilterCustomerData::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return makeResponse(401, 'Unauthenticated');
            }

            return redirect()->guest(route('login'))
                ->with('error', 'Session expired, please login again.');
        });
        
    })->create();
