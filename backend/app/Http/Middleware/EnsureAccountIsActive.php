<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Coupe l'accès aux comptes désactivés par un administrateur.
 *
 * La vérification à la connexion (AuthController::login) ne suffit pas : un
 * token JWT déjà émis reste valide jusqu'à son expiration, une suspension
 * n'aurait donc aucun effet immédiat. On revalide donc à chaque requête.
 *
 * Volontairement absent des routes `auth/me` et `auth/logout` : l'utilisateur
 * suspendu doit pouvoir constater son état et fermer sa session proprement.
 */
class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && ! $user->is_active) {
            return response()->json([
                'message' => 'Votre compte a été désactivé. Contactez l\'administration.',
                'code' => 'account_suspended',
            ], 403);
        }

        return $next($request);
    }
}
