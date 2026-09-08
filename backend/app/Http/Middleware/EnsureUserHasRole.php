<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restreint une route à un ou plusieurs rôles.
 * Usage : ->middleware('role:promoter') ou ->middleware('role:admin,promoter').
 */
class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if ($user === null) {
            return response()->json(['message' => 'Non authentifié.'], 401);
        }

        if (! in_array($user->role->value, $roles, true)) {
            // Le refus nomme le compte réellement connecté, pas seulement le
            // rôle attendu : quand l'interface et la session divergent (rôle
            // modifié entre-temps, second compte ouvert dans un autre onglet),
            // c'est cette information-là qui explique le refus. « rôle requis
            // (promoter) » laissait chercher du côté des droits alors que le
            // problème est l'identité de la session.
            $expected = implode(' ou ', array_map(
                fn (string $role) => UserRole::from($role)->label(),
                $roles,
            ));

            return response()->json([
                'message' => "Action réservée aux comptes « {$expected} » — "
                    ."vous êtes connecté avec un compte « {$user->role->label()} ».",
                // Repris par l'interface pour resynchroniser la session.
                'code' => 'role_mismatch',
                'required_roles' => array_values($roles),
                'current_role' => $user->role->value,
            ], 403);
        }

        return $next($request);
    }
}
