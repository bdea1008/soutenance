<?php

use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\EnsureKycVerified;
use App\Http\Middleware\EnsureUserHasRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => EnsureUserHasRole::class,
            'kyc.verified' => EnsureKycVerified::class,
            'account.active' => EnsureAccountIsActive::class,
        ]);

        // API pure : un invité non authentifié ne doit jamais être redirigé
        // vers une route web `login` (inexistante) — on renvoie du JSON 401.
        $middleware->redirectGuestsTo(
            fn (Request $request) => $request->is('api/*') ? null : '/login'
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        // Champs qu'aucun rapport d'erreur ne doit recopier. Les coordonnées
        // de carte s'ajoutent aux mots de passe : elles ne sont pas conservées
        // en base (voir App\Support\PaymentInstrument), il serait absurde
        // qu'elles ressortent dans un journal d'exception à la première
        // erreur de saisie.
        $exceptions->dontFlash([
            'current_password',
            'password',
            'password_confirmation',
            'card_number',
            'card_cvc',
        ]);
    })->create();
