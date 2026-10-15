<?php

use App\Http\Middleware\RedirectToSetupWhenNoAdmin;
use App\Http\Middleware\RefuseRealDataAtDemoAddress;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
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
        // Before anything else: the address of the demo never shows the real data.
        $middleware->prepend(RefuseRealDataAtDemoAddress::class);

        // Until the admin account exists there is nobody to sign in, so the
        // first-run redirect must run before the sign-in check.
        $middleware->prependToPriorityList(
            before: AuthenticatesRequests::class,
            prepend: RedirectToSetupWhenNoAdmin::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
