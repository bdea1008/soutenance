<?php

use App\Models\SimulatedEmail;
use Illuminate\Support\Facades\Route;

// Le backend ne sert qu'une API : cette unique page web dit sur quoi on est
// tombé et renvoie vers l'interface React.
Route::get('/', function () {
    return view('welcome', [
        'frontend' => rtrim(config('app.frontend_url', 'http://localhost:5173'), '/'),
    ]);
});

/*
 * Prévisualisation d'un message de la boîte d'envoi simulée.
 *
 * Route web et non API : ce qu'on rend ici, c'est le message tel qu'il serait
 * arrivé dans une boîte mail — du HTML, ouvert dans un onglet, pas du JSON.
 * C'est ce qui rend la simulation démontrable : le lien de réinitialisation se
 * clique depuis le message même, comme dans un vrai client de messagerie.
 *
 * L'identifiant est l'UUID du message, jamais son auto-incrément : sans cela,
 * n'importe qui pourrait parcourir /mail-simule/1, 2, 3… et récupérer les
 * liens de réinitialisation de tous les comptes.
 *
 * Se coupe avec MAIL_SIMULATION_REVEAL=false — à faire dès qu'un vrai serveur
 * de messagerie est branché.
 */
Route::get('/mail-simule/{uuid}', function (string $uuid) {
    abort_unless(config('mail_simulation.reveal'), 404);

    $email = SimulatedEmail::where('uuid', $uuid)->firstOrFail();

    return view('emails.preview', ['email' => $email]);
})->name('mail.simulated.preview');
