<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Barrière de vérification documentaire complémentaire (KYC).
 * Exigée avant l'investissement effectif ou la publication effective d'un
 * projet (§2, niveau 3 du parcours utilisateur).
 */
class EnsureKycVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return response()->json(['message' => 'Non authentifié.'], 401);
        }

        if (! $user->isKycVerified()) {
            return response()->json([
                'message' => 'Vérification KYC requise avant cette action.',
                'kyc_status' => $user->kyc_status->value,
            ], 403);
        }

        return $next($request);
    }
}
