<?php

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
        $middleware->alias(['role' => \App\Http\Middleware\EnsureRole::class]);

        // Staff and customers have separate sign-in pages.
        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('staff', 'staff/*')
            ? route('staff.login')
            : route('customer.login'));
        $middleware->redirectUsersTo(fn (Request $request) => $request->is('staff', 'staff/*')
            ? route('dashboard')
            : route('portal.dashboard'));

        // Razorpay calls this server-to-server; it is verified by signature instead.
        $middleware->validateCsrfTokens(except: ['webhooks/razorpay']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
