<?php

use App\Http\Controllers\Api\Admin\DocumentReviewController;
use App\Http\Controllers\Api\Admin\FinanceController;
use App\Http\Controllers\Api\Admin\MailboxController;
use App\Http\Controllers\Api\Admin\OverviewController;
use App\Http\Controllers\Api\Admin\ProjectModerationController;
use App\Http\Controllers\Api\Admin\UserController as AdminUserController;
use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ContributionController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PasswordResetController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\ProjectDossierController;
use App\Http\Controllers\Api\ProjectReviewController;
use App\Http\Controllers\Api\SiteReportController;
use App\Http\Controllers\Api\StatsController;
use App\Http\Controllers\Api\SubscriptionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Routes API — AndTabbax
|--------------------------------------------------------------------------
| Toutes les routes sont préfixées par /api (voir bootstrap/app.php).
| Le guard par défaut est « api » (JWT).
*/

Route::get('/health', fn () => response()->json([
    'status' => 'ok',
    'app' => config('app.name'),
    'time' => now()->toIso8601String(),
]));

// --- Authentification (public) -------------------------------------------
Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);

    // --- Mot de passe oublié (§10) ---------------------------------------
    // Vérification d'identité par email : le lien envoyé à l'adresse déclarée
    // du compte est la seule preuve exigée, il faut donc que ces routes
    // restent publiques — celui qui les appelle ne peut pas se connecter.
    //
    // Limitation de débit explicite : ce sont les seules routes publiques qui
    // déclenchent un envoi et lisent la table des comptes. Le courtier Laravel
    // limite déjà une même adresse à une demande par minute ; le throttle
    // ci-dessous borne en plus le nombre d'adresses testées depuis une même IP.
    Route::middleware('throttle:10,1')->group(function () {
        Route::post('password/forgot', [PasswordResetController::class, 'forgot']);
        Route::get('password/check', [PasswordResetController::class, 'check']);
        Route::post('password/reset', [PasswordResetController::class, 'reset']);
    });

    // Requiert un token JWT valide.
    Route::middleware('auth:api')->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('refresh', [AuthController::class, 'refresh']);
    });
});

// --- Niveau 1 : espace public (visiteur non authentifié) -----------------
Route::get('stats', [StatsController::class, 'index']);
Route::get('projects', [ProjectController::class, 'index']);          // aperçu public
Route::get('subscription-plans', [SubscriptionController::class, 'plans']); // page Tarifs

// --- Niveau 2 : utilisateur authentifié ----------------------------------
// `account.active` s'applique à tout le niveau 2 : une suspension prononcée
// par l'administration doit prendre effet immédiatement, sans attendre
// l'expiration du token déjà émis. Les routes `auth/me` et `auth/logout`
// restent volontairement accessibles (constater son état, fermer sa session).
Route::middleware(['auth:api', 'account.active'])->group(function () {
    // Détail complet d'un projet.
    Route::get('projects/{project}', [ProjectController::class, 'show']);

    // Espace investisseur : historique des contributions.
    Route::get('me/contributions', [ContributionController::class, 'index']);

    // Tableau de bord analytique (§7.6) — lecture adaptée au rôle.
    Route::get('me/analytics', [AnalyticsController::class, 'index']);

    // --- Notifications applicatives (§7.7) --------------------------------
    Route::get('me/notifications', [NotificationController::class, 'index']);
    Route::get('me/notifications/unread', [NotificationController::class, 'unread']);
    Route::post('me/notifications/read-all', [NotificationController::class, 'readAll']);
    Route::post('me/notifications/{notification}/read', [NotificationController::class, 'read']);
    Route::delete('me/notifications/{notification}', [NotificationController::class, 'destroy']);

    // --- Suivi de chantier (§7.5) : lecture ouverte au niveau 2 -----------
    // La transparence sur l'avancement est ce qui met en confiance avant
    // d'investir : tout utilisateur authentifié peut consulter le journal.
    Route::get('projects/{project}/reports', [SiteReportController::class, 'index']);
    Route::get('reports/{report}/photos/{index}', [SiteReportController::class, 'photo'])
        ->whereNumber('index');

    // --- Avis d'investisseurs (§2, extension d'« Investir ») --------------
    // Lecture ouverte au niveau 2, même règle de visibilité que le reste de
    // la fiche projet. Le dépôt est réservé aux investisseurs ayant
    // réellement investi — voir le groupe `role:investor` plus bas.
    Route::get('projects/{project}/reviews', [ProjectReviewController::class, 'index']);

    // --- Dossier de l'opération (niveau 3, §7.2) --------------------------
    // Checklist des pièces propres à un projet. Confidentiel : le contrôleur
    // le réserve au porteur, à l'administration et au rôle juridique.
    Route::get('projects/{project}/dossier', [ProjectDossierController::class, 'index']);

    // --- Dossier KYC : ouvert à tout utilisateur authentifié --------------
    // C'est précisément l'étape qui permet de passer au niveau 3.
    Route::get('me/documents', [DocumentController::class, 'index']);
    Route::post('me/documents', [DocumentController::class, 'store']);
    Route::delete('me/documents/{document}', [DocumentController::class, 'destroy']);
    // Téléchargement réservé au propriétaire et à l'administrateur.
    Route::get('documents/{document}/download', [DocumentController::class, 'download']);

    // --- Administration : back-office de supervision (§5) ------------------
    Route::middleware('role:admin')->prefix('admin')->group(function () {
        // Console d'accueil : files de travail et activité récente.
        Route::get('overview', [OverviewController::class, 'index']);

        // Modération des pièces KYC (§7.5).
        Route::get('documents', [DocumentReviewController::class, 'index']);
        Route::get('documents/stats', [DocumentReviewController::class, 'stats']);
        Route::post('documents/{document}/review', [DocumentReviewController::class, 'review']);

        // Gestion des comptes (§7.1). Les routes littérales précèdent la route
        // à paramètre, sinon « stats » serait pris pour un identifiant.
        Route::get('users/stats', [AdminUserController::class, 'stats']);
        Route::get('users', [AdminUserController::class, 'index']);
        Route::post('users', [AdminUserController::class, 'store']);
        Route::get('users/{user}', [AdminUserController::class, 'show'])->whereNumber('user');
        Route::patch('users/{user}/status', [AdminUserController::class, 'setStatus'])->whereNumber('user');
        Route::patch('users/{user}/role', [AdminUserController::class, 'setRole'])->whereNumber('user');
        Route::delete('users/{user}', [AdminUserController::class, 'destroy'])->whereNumber('user');

        // Supervision des projets (§7.2), tous statuts confondus. La
        // décision (moderate) reste ici, réservée à l'administrateur — la
        // lecture est ouverte au rôle juridique juste en dessous, jamais
        // l'écriture : il consulte, il n'arbitre pas.
        Route::post('projects/{project}/moderate', [ProjectModerationController::class, 'moderate'])
            ->whereNumber('project');

        // Boîte d'envoi simulée (config/mail_simulation.php) : ce que la
        // plateforme aurait envoyé, tant qu'aucun serveur de messagerie n'est
        // branché. Les noms de route servent au SimulatedEmailResource, qui
        // n'attache le corps du message qu'au détail.
        Route::get('emails', [MailboxController::class, 'index'])->name('admin.emails.index');
        Route::delete('emails', [MailboxController::class, 'destroyAll'])->name('admin.emails.clear');
        Route::get('emails/{email}', [MailboxController::class, 'show'])
            ->whereNumber('email')
            ->name('admin.emails.show');

        // Suivi financier (§7.4, §16.2.a) — paiements simulés en MVP.
        Route::get('finance/summary', [FinanceController::class, 'summary']);
        Route::get('finance/subscriptions', [FinanceController::class, 'subscriptions']);
        Route::get('finance/payments', [FinanceController::class, 'payments']);
    });

    // --- Rôle juridique & conformité : lecture seule des projets (§5) ------
    // Même supervision que l'administrateur (tous statuts confondus, pour
    // pouvoir vérifier un dossier avant sa validation finale), mais sans
    // aucun droit de décision — d'où un groupe séparé plutôt qu'un simple
    // élargissement du groupe `role:admin` ci-dessus, qui aurait aussi ouvert
    // les comptes, les pièces KYC et les finances.
    Route::middleware('role:admin,legal')->prefix('admin')->group(function () {
        Route::get('projects/stats', [ProjectModerationController::class, 'stats']);
        Route::get('projects', [ProjectModerationController::class, 'index']);
    });

    // Espace promoteur : ses propres projets, brouillons inclus.
    // Pas de garde KYC ici — un promoteur doit pouvoir consulter ses projets
    // même tant que sa vérification est en attente.
    //
    // L'administrateur en est exclu : il n'a pas de projets à lui ni
    // d'abonnement à souscrire, et il supervise ceux des autres par les routes
    // /admin. Lui laisser ces droits en ferait juge et partie.
    Route::middleware('role:promoter')->group(function () {
        Route::get('me/projects', [ProjectController::class, 'mine']);

        // Brouillon de projet : accessible sans dossier constitué. Le promoteur
        // prépare son opération pendant que ses pièces sont examinées ; c'est la
        // publication, plus bas, qui exige le dossier complet.
        Route::post('projects', [ProjectController::class, 'store']);
        Route::put('projects/{project}', [ProjectController::class, 'update']);
        Route::delete('projects/{project}', [ProjectController::class, 'destroy']);

        // Abonnement promoteur (§16.2.a) — prérequis de la publication.
        // Souscrire ne requiert pas le KYC : on peut payer avant d'être vérifié.
        Route::get('me/subscription', [SubscriptionController::class, 'current']);
        Route::get('me/payments', [SubscriptionController::class, 'payments']);
        Route::post('subscriptions', [SubscriptionController::class, 'store']);
        Route::post('me/subscription/renew', [SubscriptionController::class, 'renew']);
        Route::post('me/subscription/cancel', [SubscriptionController::class, 'cancel']);
    });

    // --- Promoteur : gestion de projets (rôle promoteur + KYC) ------------
    // Créer, publier ou supprimer un projet appartient au promoteur seul.
    // L'administration met un projet en ligne ou le retire par
    // POST /admin/projects/{project}/moderate, qui trace la décision et
    // notifie l'intéressé — jamais en se substituant à lui.
    Route::middleware(['role:promoter', 'kyc.verified'])->group(function () {
        // Publication effective : requiert en plus un abonnement actif et un
        // dossier d'opération complet (vérifiés en contrôleur). Le `kyc.verified`
        // du groupe porte ici le **dossier de l'opérateur** (niveau 2).
        Route::post('projects/{project}/publish', [ProjectController::class, 'publish']);

        // Rapports de chantier : réservés au promoteur du projet (vérifié en contrôleur).
        Route::post('projects/{project}/reports', [SiteReportController::class, 'store']);
        Route::put('reports/{report}', [SiteReportController::class, 'update']);
        Route::delete('reports/{report}', [SiteReportController::class, 'destroy']);
    });

    // --- Investisseur : investir (rôle investisseur + KYC) ----------------
    // L'administrateur en est exclu : il arbitre les projets, il n'y place pas
    // son argent.
    Route::middleware(['role:investor', 'kyc.verified'])->group(function () {
        Route::post('projects/{project}/invest', [ContributionController::class, 'store']);

        // Noter / commenter (extension d'« Investir ») : réservé à qui a
        // effectivement investi, vérifié en contrôleur (le middleware ici
        // garde seulement le rôle et le KYC, pas le lien à un projet précis).
        Route::post('projects/{project}/reviews', [ProjectReviewController::class, 'store']);
        Route::delete('projects/{project}/reviews', [ProjectReviewController::class, 'destroy']);
    });
});
