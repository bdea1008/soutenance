<?php

namespace App\Providers;

use App\Mail\Transport\SimulatedTransport;
use Illuminate\Support\Facades\Mail;
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
        // Transport « simulated » (MAIL_MAILER=simulated) : dépose le message
        // dans la boîte d'envoi simulée au lieu de l'expédier. Voir
        // config/mail_simulation.php pour le pourquoi et pour repasser en
        // envoi réel.
        Mail::extend('simulated', fn (array $config) => new SimulatedTransport);
    }
}
