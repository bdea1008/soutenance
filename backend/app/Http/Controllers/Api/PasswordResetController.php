<?php

namespace App\Http\Controllers\Api;

use App\Enums\NotificationType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Mail\PasswordChangedMail;
use App\Models\SimulatedEmail;
use App\Models\User;
use App\Services\Notifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * Mot de passe oublié (§10) — vérification d'identité par email.
 *
 * Trois temps, tous publics : on demande un lien, on vérifie qu'il est encore
 * bon, on pose le nouveau mot de passe. Le jeton est la seule preuve
 * d'identité : il est envoyé à l'adresse déclarée du compte, donc seul
 * quelqu'un qui accède à cette boîte peut aller au bout.
 *
 * Le stockage et la vérification du jeton reposent sur le courtier de
 * réinitialisation de Laravel (table `password_reset_tokens`) : jeton haché en
 * base, expiration à 60 minutes, usage unique, limitation des demandes
 * répétées. Réécrire cette mécanique à la main n'aurait apporté que des
 * occasions de se tromper.
 *
 * L'envoi lui-même est simulé (config/mail_simulation.php) : le message est
 * réellement composé et rendu, puis déposé dans une boîte consultable au lieu
 * d'être remis à un serveur SMTP.
 */
class PasswordResetController extends Controller
{
    public function __construct(private readonly Notifier $notifier) {}

    /**
     * Étape 1 — demander un lien.
     *
     * La réponse est volontairement la même que l'adresse existe ou non :
     * autrement, ce point d'entrée public deviendrait un moyen de savoir qui
     * est inscrit sur la plateforme (et donc qui investit) à partir d'une
     * simple liste d'adresses.
     */
    public function forgot(ForgotPasswordRequest $request): JsonResponse
    {
        $email = $request->validated()['email'];

        $generic = [
            'message' => 'Si un compte correspond à cette adresse, un lien de réinitialisation'
                .' vient de lui être envoyé. Il expire dans '
                .(int) config('auth.passwords.users.expire', 60).' minutes.',
        ];

        $user = User::where('email', $email)->first();

        // Compte inconnu ou désactivé par l'administration : rien n'est envoyé,
        // mais la réponse ne le dit pas. Un compte suspendu ne doit pas pouvoir
        // se redonner un accès en passant par la porte du mot de passe oublié.
        if ($user === null || ! $user->is_active) {
            return response()->json($generic);
        }

        $status = Password::broker()->sendResetLink(['email' => $email]);

        if ($status === Password::RESET_THROTTLED) {
            return response()->json([
                'message' => 'Un lien vient déjà d’être envoyé à cette adresse.'
                    .' Patientez une minute avant d’en redemander un.',
                'code' => 'reset_throttled',
            ], 429);
        }

        if ($status !== Password::RESET_LINK_SENT) {
            Log::warning('Envoi du lien de réinitialisation impossible', [
                'status' => $status,
                'user_id' => $user->id,
            ]);

            return response()->json($generic);
        }

        return response()->json(
            $generic + array_filter(['simulation' => $this->simulationOf($email)])
        );
    }

    /**
     * Étape 2 — le lien est-il encore valable ?
     *
     * Appelé à l'ouverture du formulaire, pour annoncer un lien périmé avant
     * que l'utilisateur ne choisisse et saisisse deux fois un mot de passe
     * qui sera refusé de toute façon.
     */
    public function check(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'token' => ['required', 'string'],
        ]);

        $email = mb_strtolower(trim($data['email']));
        $user = User::where('email', $email)->first();

        $valid = $user !== null
            && $user->is_active
            && Password::broker()->getRepository()->exists($user, $data['token']);

        return response()->json([
            'valid' => $valid,
            'email' => $email,
            'message' => $valid
                ? null
                : 'Ce lien est expiré ou a déjà été utilisé. Demandez-en un nouveau.',
        ]);
    }

    /** Étape 3 — poser le nouveau mot de passe. */
    public function reset(ResetPasswordRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = User::where('email', $data['email'])->first();

        if ($user !== null && ! $user->is_active) {
            return response()->json([
                'message' => 'Ce compte est désactivé. Contactez l’administration.',
                'code' => 'account_suspended',
            ], 403);
        }

        $status = Password::broker()->reset($data, function (User $user, string $password): void {
            // Le cast `hashed` du modèle se charge du hachage — même chemin
            // qu'à l'inscription, pas de Hash::make dupliqué ici.
            // `remember_token` est régénéré pour couper les sessions « se
            // souvenir de moi » ouvertes avec l'ancien mot de passe.
            $user->forceFill([
                'password' => $password,
                'remember_token' => Str::random(60),
            ])->save();
        });

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json([
                'message' => 'Ce lien est expiré ou a déjà été utilisé. Demandez-en un nouveau.',
                'code' => 'invalid_token',
            ], 422);
        }

        // `Password::reset()` a consommé le jeton : le lien ne resservira pas.
        $user = $user?->fresh() ?? User::where('email', $data['email'])->first();

        $this->confirmChange($user);

        return response()->json([
            'message' => 'Mot de passe modifié. Vous pouvez maintenant vous connecter.',
        ]);
    }

    /**
     * Accusé de changement : email de confirmation + notification applicative.
     *
     * Jamais bloquant — le mot de passe est déjà changé à ce stade, échouer
     * ici laisserait l'utilisateur devant une erreur alors que l'opération a
     * réussi. Même règle que pour les notifications des autres modules.
     */
    private function confirmChange(?User $user): void
    {
        if ($user === null) {
            return;
        }

        try {
            Mail::send(new PasswordChangedMail($user));
            $this->notifier->notify($user, NotificationType::PasswordChanged);
        } catch (\Throwable $e) {
            Log::error('Accusé de changement de mot de passe non émis', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Bloc de démonstration accompagnant la réponse : où lire le message qui
     * vient d'être « envoyé ».
     *
     * N'existe que parce que l'envoi est simulé — sans boîte mail réelle, il
     * faut bien un moyen d'ouvrir le message pour montrer que la chaîne
     * fonctionne. Se coupe avec MAIL_SIMULATION_REVEAL=false, ce qu'il faut
     * faire dès qu'un vrai serveur SMTP est branché : ce bloc contient le lien
     * de réinitialisation, il ne doit jamais sortir en production.
     *
     * @return array<string, mixed>|null
     */
    private function simulationOf(string $email): ?array
    {
        if (! config('mail_simulation.reveal') || config('mail.default') !== 'simulated') {
            return null;
        }

        $message = SimulatedEmail::addressedTo($email)->latest('id')->first();

        if ($message === null) {
            return null;
        }

        return [
            'notice' => 'Démonstration : l’envoi d’emails est simulé sur cet environnement.'
                .' Le message a bien été composé, il est consultable ci-dessous au lieu'
                .' d’être remis à un serveur de messagerie.',
            'to' => $message->to_email,
            'subject' => $message->subject,
            'sent_at' => $message->created_at?->toIso8601String(),
            'preview_url' => $message->previewUrl(),
            'reset_url' => $message->action_url,
        ];
    }
}
