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

        // Unauthenticated users → login. Each isolated area redirects to its own
        // sign-in: admin → admin login, supplier portal → supplier login.
        $middleware->redirectGuestsTo(function (Request $request) {
            if ($request->is('admin') || $request->is('admin/*')) {
                return route('admin.login');
            }

            if ($request->is('supplier') || $request->is('supplier/*')) {
                return route('supplier.login');
            }

            return route('login');
        });
        // Already-authenticated users hitting a guest-only route go to their
        // own area's home, not the end-user dashboard.
        $middleware->redirectUsersTo(function (Request $request) {
            if ($request->is('admin') || $request->is('admin/*')) {
                return route('admin.dashboard');
            }

            if ($request->is('supplier') || $request->is('supplier/*')) {
                return route('supplier.dashboard');
            }

            return '/dashboard';
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
