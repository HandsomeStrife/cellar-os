<?php

use App\Http\Middleware\EnsureFeatureAccess;
use App\Http\Middleware\SecurityHeaders;
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
        $middleware->web(append: [
            SecurityHeaders::class,
        ]);

        $middleware->alias([
            'feature' => EnsureFeatureAccess::class,
        ]);

        // Unauthenticated users → login (admin area → admin login);
        // already-authenticated users hitting guest-only routes → dashboard.
        $middleware->redirectGuestsTo(
            fn (Request $request) => $request->is('admin') || $request->is('admin/*') ? route('admin.login') : route('login')
        );
        $middleware->redirectUsersTo('/dashboard');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
