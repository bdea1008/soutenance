<?php

namespace App\Http\Controllers\Api;

use App\Enums\KycStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Support\PromoterIdentity;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Authentification JWT (§10). Première barrière d'accès au contenu détaillé
 * de la plateforme (niveau 2 du parcours utilisateur, §6).
 */
class AuthController extends Controller
{
    /** Inscription investisseur ou promoteur. */
    public function register(RegisterRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = User::create([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'name' => trim($data['first_name'].' '.$data['last_name']),
            'email' => $data['email'],
            'phone' => $data['phone'],
            'password' => $data['password'], // hashé via cast
            'role' => $data['role'],
            // Sous-type de promoteur et structure porteuse, remis à vide pour
            // tout autre profil (voir PromoterIdentity).
            ...PromoterIdentity::columns($data),
            'kyc_status' => KycStatus::None->value,
            'country' => $data['country'] ?? null,
            'city' => $data['city'] ?? null,
            // Explicite plutôt que de compter sur le défaut de colonne :
            // sans cette ligne, l'objet en mémoire (et donc la réponse JSON)
            // ne porte pas la valeur tant qu'il n'est pas rechargé — la
            // création réussit mais annonce un compte inactif à tort.
            'is_active' => true,
        ]);

        $token = Auth::login($user);

        return $this->respondWithToken($token, $user, 201);
    }

    /** Connexion par email + mot de passe. */
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();

        $token = Auth::attempt($credentials);

        if ($token === false) {
            return response()->json(['message' => 'Identifiants invalides.'], 401);
        }

        $user = Auth::user();

        if (! $user->is_active) {
            Auth::logout();

            return response()->json(['message' => 'Compte désactivé.'], 403);
        }

        return $this->respondWithToken($token, $user);
    }

    /** Profil de l'utilisateur authentifié. */
    public function me(): JsonResponse
    {
        return response()->json([
            'user' => new UserResource(Auth::user()),
        ]);
    }

    /** Déconnexion (invalide le token courant). */
    public function logout(): JsonResponse
    {
        Auth::logout();

        return response()->json(['message' => 'Déconnexion réussie.']);
    }

    /** Rafraîchit le token JWT. */
    public function refresh(): JsonResponse
    {
        $token = Auth::refresh();

        return $this->respondWithToken($token, Auth::user());
    }

    private function respondWithToken(string $token, User $user, int $status = 200): JsonResponse
    {
        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => Auth::factory()->getTTL() * 60, // secondes
            'user' => new UserResource($user),
        ], $status);
    }
}
