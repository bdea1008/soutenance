<?php

namespace Database\Seeders;

use App\Enums\ContributionStatus;
use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\NotificationType;
use App\Enums\PaymentProvider;
use App\Enums\PaymentPurpose;
use App\Enums\PaymentStatus;
use App\Enums\ProjectStatus;
use App\Enums\PromoterType;
use App\Enums\SubscriptionStatus;
use App\Enums\SubscriptionTier;
use App\Enums\VerificationContext;
use App\Models\Contribution;
use App\Models\Payment;
use App\Models\Project;
use App\Models\PromoterSubscription;
use App\Models\SiteReport;
use App\Models\Transaction;
use App\Models\User;
use App\Models\VerificationDocument;
use App\Services\AiScoringClient;
use App\Services\Notifier;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // --- Administrateur -------------------------------------------------
        $admin = User::factory()->admin()->kycVerified()->create([
            'first_name' => 'Admin',
            'last_name' => 'AndTabbax',
            'name' => 'Admin AndTabbax',
            'email' => 'admin@andtabbax.sn',
        ]);

        // --- Rôle juridique & conformité -------------------------------------
        // Rôle interne : jamais auto-attribuable, uniquement créé par un
        // administrateur (§5). `kycVerified()` n'a pas d'effet ici — le rôle
        // n'est soumis à aucun document — gardé pour la cohérence des seeds.
        User::factory()->legal()->create([
            'first_name' => 'Aïssatou',
            'last_name' => 'Fall',
            'name' => 'Aïssatou Fall',
            'email' => 'juridique@andtabbax.sn',
        ]);

        // --- Investisseur de démo (diaspora) --------------------------------
        $investor = User::factory()->kycVerified()->create([
            'first_name' => 'Awa',
            'last_name' => 'Diallo',
            'name' => 'Awa Diallo',
            'email' => 'investisseur@andtabbax.sn',
            'country' => 'France',
            'city' => 'Paris',
        ]);

        // Le statut « vérifié » découle des pièces déposées : sans elles, la
        // première décision d'un administrateur ferait retomber ces comptes
        // en « non vérifié ». On leur crée donc un dossier KYC complet.
        $this->seedApprovedKyc($investor, $admin);

        // --- Promoteur de démo avec abonnement actif ------------------------
        // Inscrit il y a plus de trois ans : l'ancienneté et les opérations
        // livrées sont des variables du scoring IA (§8.1), un compte créé le
        // jour même donnerait des scores artificiellement médiocres.
        $promoter = User::factory()->promoter()->kycVerified()->create([
            'first_name' => 'Moussa',
            'last_name' => 'Ndiaye',
            'name' => 'Moussa Ndiaye',
            'email' => 'promoteur@andtabbax.sn',
            'created_at' => now()->subMonths(40),
        ]);

        $this->seedApprovedKyc($promoter, $admin);

        // Historique d'abonnement du promoteur de démo. Un premier contrat
        // Essentiel arrivé à échéance puis un Premium en cours : le parc vu par
        // l'administration (/admin/abonnements) doit montrer autre chose qu'une
        // seule ligne active, sinon ses filtres par état n'ont rien à filtrer.
        $this->seedSubscription(
            $promoter,
            SubscriptionTier::Basic,
            SubscriptionStatus::Expired,
            now()->subMonths(7),
            now()->subMonths(6),
            PaymentProvider::OrangeMoney,
            'DEMO01',
        );

        // Abonnement courant : sa date d'activation est celle de la
        // notification « abonnement activé » (J-25) de seedNotifications().
        $premium = $this->seedSubscription(
            $promoter,
            SubscriptionTier::Premium,
            SubscriptionStatus::Active,
            now()->subDays(25),
            now()->subDays(25)->addMonth(),
            PaymentProvider::Wave,
            'DEMO02',
        );

        // Renouvelé avant l'échéance, comme le ferait SubscriptionController::renew.
        // Son échéance repart ainsi à plus d'un mois : le promoteur de démo doit
        // pouvoir publier un projet à tout moment, une échéance seedée à quelques
        // jours l'en empêcherait dès la semaine suivante.
        $this->seedSubscriptionRenewal($premium, now()->subDays(5), PaymentProvider::Wave, 'DEMO05');

        // --- Second promoteur : abonné, sans projet en ligne ----------------
        // Le parc d'abonnements n'a de sens qu'à plusieurs titulaires : elle
        // illustre aussi le changement de palier, qui se fait en résiliant puis
        // en souscrivant à nouveau (pas d'évolution directe en MVP).
        $secondPromoter = User::factory()->promoter()->kycVerified()->create([
            'first_name' => 'Fatou',
            'last_name' => 'Sarr',
            'name' => 'Fatou Sarr',
            'email' => 'promoteur2@andtabbax.sn',
            'city' => 'Saint-Louis',
            'created_at' => now()->subMonths(9),
        ]);

        $this->seedApprovedKyc($secondPromoter, $admin);

        $this->seedSubscription(
            $secondPromoter,
            SubscriptionTier::Premium,
            SubscriptionStatus::Cancelled,
            now()->subMonths(3),
            now()->subMonths(2),
            PaymentProvider::Wave,
            'DEMO03',
        );

        $this->seedSubscription(
            $secondPromoter,
            SubscriptionTier::Basic,
            SubscriptionStatus::Active,
            now()->subDays(10),
            now()->addDays(20),
            PaymentProvider::OrangeMoney,
            'DEMO04',
        );

        // --- Projets immobiliers de démo (visibles page d'accueil) ----------
        $projects = [
            [
                'title' => 'Résidence Les Almadies',
                'summary' => 'Immeuble résidentiel de standing de 12 appartements aux Almadies, Dakar.',
                'category' => 'résidentiel',
                'region' => 'Dakar', 'city' => 'Dakar',
                'latitude' => 14.7458, 'longitude' => -17.5136,
                'funding_goal' => 150_000_000, 'amount_raised' => 92_000_000,
                'min_investment' => 500_000, 'expected_return_rate' => 14.5,
                'duration_months' => 18, 'status' => ProjectStatus::Published,
                'deed_verified' => true,
            ],
            [
                'title' => 'Complexe commercial Thiès Centre',
                'summary' => 'Galerie commerciale de 20 locaux au cœur de Thiès.',
                'category' => 'commercial',
                'region' => 'Thiès', 'city' => 'Thiès',
                'latitude' => 14.7910, 'longitude' => -16.9256,
                'funding_goal' => 80_000_000, 'amount_raised' => 80_000_000,
                'min_investment' => 250_000, 'expected_return_rate' => 12.0,
                'duration_months' => 12, 'status' => ProjectStatus::Funded,
                'deed_verified' => true,
            ],
            [
                'title' => 'Lotissement Diamniadio Nord',
                'summary' => 'Viabilisation et vente de 40 parcelles à Diamniadio.',
                'category' => 'terrain',
                'region' => 'Dakar', 'city' => 'Diamniadio',
                'latitude' => 14.7280, 'longitude' => -17.1840,
                'funding_goal' => 60_000_000, 'amount_raised' => 21_500_000,
                'min_investment' => 200_000, 'expected_return_rate' => 18.0,
                'duration_months' => 10, 'status' => ProjectStatus::Published,
                // Pas de titre foncier validé : le scoring doit s'en ressentir.
                'deed_verified' => false,
            ],
            [
                // Opération déjà livrée : elle donne au promoteur de démo un
                // antécédent, signal de confiance déterminant pour le scoring (§8.1).
                'title' => 'Résidence Ngor Village',
                'summary' => 'Petit collectif de 6 appartements à Ngor, livré et cédé aux acquéreurs.',
                'category' => 'résidentiel',
                'region' => 'Dakar', 'city' => 'Dakar',
                'latitude' => 14.7520, 'longitude' => -17.5130,
                'funding_goal' => 70_000_000, 'amount_raised' => 70_000_000,
                'min_investment' => 350_000, 'expected_return_rate' => 13.0,
                'duration_months' => 15, 'status' => ProjectStatus::Completed,
                'deed_verified' => true, 'delivered_months_ago' => 8,
            ],
        ];

        foreach ($projects as $data) {
            // Une opération livrée s'inscrit dans le passé, pas dans l'avenir.
            $ago = $data['delivered_months_ago'] ?? null;

            $project = Project::create([
                'promoter_id' => $promoter->id,
                'title' => $data['title'],
                'slug' => Str::slug($data['title']),
                'summary' => $data['summary'],
                'description' => $data['summary'].' Projet piloté par un promoteur partenaire vérifié.',
                'category' => $data['category'],
                'region' => $data['region'],
                'city' => $data['city'],
                'latitude' => $data['latitude'],
                'longitude' => $data['longitude'],
                'funding_goal' => $data['funding_goal'],
                'amount_raised' => $data['amount_raised'],
                'min_investment' => $data['min_investment'],
                'expected_return_rate' => $data['expected_return_rate'],
                'duration_months' => $data['duration_months'],
                'status' => $data['status']->value,
                'published_at' => $ago
                    ? now()->subMonths($ago + $data['duration_months'] + 2)
                    : now(),
            ]);

            // Dossier d'opération validé : prérequis de la publication, et le
            // titre foncier vérifié entre dans le calcul du score (§8.1). Un
            // projet sans dossier reste réaliste — c'est un dossier en cours de
            // constitution.
            if ($data['deed_verified']) {
                $this->seedProjectDossier($project, $promoter, $admin);
            }
        }

        // --- Particulier de démo : le second sous-type de promoteur ---------
        // Il ne monte pas d'opération, il finance son propre bien : dossier
        // fondé sur ses revenus, palier « Particulier », un seul projet en
        // ligne. Sans lui, seul le profil société serait démontrable.
        $individual = User::factory()->promoter(PromoterType::Individual)->kycVerified()->create([
            'first_name' => 'Ousmane',
            'last_name' => 'Bâ',
            'name' => 'Ousmane Bâ',
            'email' => 'particulier@andtabbax.sn',
            'city' => 'Thiès',
            'created_at' => now()->subMonths(4),
        ]);

        $this->seedApprovedKyc($individual, $admin);

        $this->seedSubscription(
            $individual,
            SubscriptionTier::Individual,
            SubscriptionStatus::Active,
            now()->subDays(12),
            now()->addDays(18),
            PaymentProvider::OrangeMoney,
            'DEMO06',
        );

        $house = Project::create([
            'promoter_id' => $individual->id,
            'title' => 'Maison familiale à Thiès',
            'slug' => 'maison-familiale-thies',
            'summary' => 'Construction d’une maison individuelle R+1 sur un terrain familial à Thiès.',
            'description' => 'Projet porté par un particulier : construction de sa résidence '
                .'principale sur un terrain dont il est propriétaire, avec un apport personnel déjà constitué.',
            'category' => 'résidentiel',
            'region' => 'Thiès', 'city' => 'Thiès',
            'latitude' => 14.7886, 'longitude' => -16.9260,
            'funding_goal' => 18_000_000, 'amount_raised' => 4_500_000,
            'min_investment' => 250_000, 'expected_return_rate' => 9.5,
            'duration_months' => 14,
            'status' => ProjectStatus::Published->value,
            'published_at' => now()->subDays(9),
        ]);

        $this->seedProjectDossier($house, $individual, $admin);

        $this->seedSiteReports($promoter);
        $this->seedContributions($investor);
        $this->seedNotifications($investor, $promoter, $secondPromoter);
        $this->scoreProjects();
    }

    /**
     * Abonnement promoteur de démonstration, avec son paiement simulé et son
     * écriture comptable (§16.2.a, §16.5).
     *
     * Reproduit ce que fait SubscriptionController::subscribe, à une chose
     * près : dates et statut sont imposés, pour reconstituer un historique.
     * Le paiement est daté du début du contrat — c'est `paid_at` qui ventile
     * le revenu dans la courbe mensuelle du suivi financier.
     */
    private function seedSubscription(
        User $promoter,
        SubscriptionTier $tier,
        SubscriptionStatus $status,
        Carbon $startsAt,
        Carbon $endsAt,
        PaymentProvider $provider,
        string $suffix,
    ): PromoterSubscription {
        $subscription = PromoterSubscription::create([
            'user_id' => $promoter->id,
            'tier' => $tier->value,
            'price' => $tier->monthlyPrice(),
            'currency' => 'XOF',
            'status' => $status->value,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);

        $subscription->forceFill(['created_at' => $startsAt, 'updated_at' => $startsAt])->save();

        $this->seedSubscriptionPayment(
            $subscription,
            $provider,
            $suffix,
            $startsAt,
            'Abonnement '.$tier->label(),
        );

        return $subscription;
    }

    /**
     * Renouvellement d'un abonnement seedé : prolonge l'échéance d'une période
     * et enregistre le paiement correspondant, comme le fait
     * SubscriptionController::renew.
     */
    private function seedSubscriptionRenewal(
        PromoterSubscription $subscription,
        Carbon $paidAt,
        PaymentProvider $provider,
        string $suffix,
    ): void {
        // On prolonge depuis l'échéance en cours, pas depuis la date de
        // paiement : renouveler en avance ne fait pas perdre de jours.
        $subscription->update(['ends_at' => $subscription->ends_at->copy()->addMonth()]);

        $this->seedSubscriptionPayment(
            $subscription,
            $provider,
            $suffix,
            $paidAt,
            'Renouvellement abonnement '.$subscription->tier->label(),
        );
    }

    /** Paiement simulé d'un abonnement et son écriture comptable (§16.5). */
    private function seedSubscriptionPayment(
        PromoterSubscription $subscription,
        PaymentProvider $provider,
        string $suffix,
        Carbon $paidAt,
        string $label,
    ): void {
        $payment = Payment::create([
            'user_id' => $subscription->user_id,
            'subscription_id' => $subscription->id,
            'purpose' => PaymentPurpose::Subscription->value,
            'provider' => $provider->value,
            'amount' => $subscription->price,
            'currency' => $subscription->currency,
            'status' => PaymentStatus::Simulated->value,
            'reference' => 'SUB-'.$paidAt->format('Ymd').'-'.$suffix,
            'provider_payload' => ['simulated' => true, 'tier' => $subscription->tier->value],
            'paid_at' => $paidAt,
        ]);

        $payment->forceFill(['created_at' => $paidAt, 'updated_at' => $paidAt])->save();

        Transaction::create([
            'payment_id' => $payment->id,
            'user_id' => $subscription->user_id,
            'direction' => 'debit',
            'amount' => $subscription->price,
            'currency' => $subscription->currency,
            'label' => $label,
            'meta' => ['subscription_id' => $subscription->id],
        ])->forceFill(['created_at' => $paidAt, 'updated_at' => $paidAt])->save();
    }

    /**
     * Journal de chantier de démonstration (§7.5) sur le projet déjà financé :
     * le suivi n'a de sens qu'une fois la collecte bouclée, et un projet
     * « en chantier » rend la page de détail bien plus parlante.
     */
    private function seedSiteReports(User $promoter): void
    {
        $project = Project::where('slug', 'complexe-commercial-thies-centre')->first();

        if ($project === null) {
            return;
        }

        $reports = [
            [
                'title' => 'Terrassement et fondations',
                'description' => "Terrassement achevé sur l'ensemble de l'emprise. Coulage des semelles "
                    ."et du radier terminé, béton contrôlé en laboratoire. Aucun retard sur le planning.",
                'progress' => 20,
                'days_ago' => 75,
                'photos' => ['chantier-fondations.png'],
            ],
            [
                'title' => 'Élévation des trois premiers niveaux',
                'description' => "Poteaux et dalles des niveaux R+1 à R+3 coulés. Livraison de la grue à "
                    ."tour effectuée. Le gros œuvre suit le calendrier annoncé aux investisseurs.",
                'progress' => 45,
                'days_ago' => 38,
                'photos' => ['chantier-elevation.png'],
            ],
            [
                'title' => 'Gros œuvre terminé, début du second œuvre',
                'description' => "Structure achevée sur cinq niveaux. Démarrage des cloisons et des "
                    ."réseaux électriques. Prochaine étape : menuiseries et façades.",
                'progress' => 68,
                'days_ago' => 6,
                'photos' => ['chantier-gros-oeuvre.png', 'chantier-elevation.png'],
            ],
        ];

        foreach ($reports as $data) {
            SiteReport::create([
                'project_id' => $project->id,
                'author_id' => $promoter->id,
                'title' => $data['title'],
                'description' => $data['description'],
                'progress_percentage' => $data['progress'],
                'photos' => array_map(
                    fn (string $fixture) => $this->copyFixturePhoto($project, $fixture),
                    $data['photos'],
                ),
                'reported_at' => now()->subDays($data['days_ago']),
            ]);
        }

        // Un projet dont le chantier a démarré n'est plus simplement « financé ».
        $project->update(['status' => ProjectStatus::InProgress->value]);
    }

    /**
     * Copie une illustration de chantier vers le disque privé, sous le même
     * nommage neutre que les photos déposées par un promoteur.
     *
     * @return array{path: string, original_name: string}
     */
    private function copyFixturePhoto(Project $project, string $fixture): array
    {
        $path = "reports/{$project->id}/".Str::uuid()->toString().'.png';

        Storage::disk('local')->put(
            $path,
            file_get_contents(database_path("seeders/fixtures/{$fixture}")),
        );

        return ['path' => $path, 'original_name' => $fixture];
    }

    /**
     * Portefeuille de l'investisseur de démo (§7.3).
     *
     * Ces montants font déjà partie du `amount_raised` des projets — le reste
     * de la collecte est le fait d'investisseurs non modélisés. On n'incrémente
     * donc rien, sous peine de compter deux fois.
     */
    private function seedContributions(User $investor): void
    {
        $placements = [
            ['slug' => 'residence-les-almadies', 'amount' => 2_500_000, 'months_ago' => 3],
            ['slug' => 'complexe-commercial-thies-centre', 'amount' => 1_000_000, 'months_ago' => 7],
        ];

        foreach ($placements as $placement) {
            $project = Project::where('slug', $placement['slug'])->first();

            if ($project === null) {
                continue;
            }

            $date = now()->subMonths($placement['months_ago']);

            Contribution::create([
                'investor_id' => $investor->id,
                'project_id' => $project->id,
                'amount' => $placement['amount'],
                'share_percentage' => round($placement['amount'] / $project->funding_goal * 100, 3),
                'estimated_return' => round($placement['amount'] * ($project->expected_return_rate / 100), 2),
                'status' => ContributionStatus::Confirmed->value,
                'is_simulated' => true,
                'confirmed_at' => $date,
                'created_at' => $date,
                'updated_at' => $date,
            ]);
        }
    }

    /**
     * Boîte de réception de démonstration (§7.7), reconstituée à partir de
     * l'historique réel des comptes : mêmes libellés qu'en production, puisque
     * c'est le `Notifier` qui les rédige. Les dates sont reculées pour que
     * l'ordre chronologique ait du sens à l'écran.
     */
    private function seedNotifications(User $investor, User $promoter, User $secondPromoter): void
    {
        $notifier = app(Notifier::class);

        $almadies = Project::where('slug', 'residence-les-almadies')->first();
        $thies = Project::where('slug', 'complexe-commercial-thies-centre')->first();
        $report = $thies?->siteReports()->orderByDesc('reported_at')->first();

        if (! $almadies || ! $thies) {
            return;
        }

        $emit = function (User $user, NotificationType $type, array $data, int $daysAgo, bool $read) use ($notifier) {
            $date = now()->subDays($daysAgo);

            $notifier->notify($user, $type, $data)->forceFill([
                'created_at' => $date,
                'updated_at' => $date,
                'sent_at' => $date,
                'read_at' => $read ? $date->copy()->addHours(3) : null,
            ])->save();
        };

        $ctx = fn (Project $p, array $extra = []) => [
            'project_id' => $p->id,
            'project_title' => $p->title,
            'url' => "/projets/{$p->id}",
            ...$extra,
        ];

        // --- Investisseur ---
        $emit($investor, NotificationType::KycVerified,
            ['role' => $investor->role->value, 'url' => '/verification'], 210, true);
        $emit($investor, NotificationType::InvestmentConfirmed,
            $ctx($thies, ['amount' => 1_000_000]), 210, true);
        $emit($investor, NotificationType::ProjectFunded, $ctx($thies), 120, true);
        $emit($investor, NotificationType::InvestmentConfirmed,
            $ctx($almadies, ['amount' => 2_500_000]), 90, true);

        if ($report) {
            $emit($investor, NotificationType::ReportPublished, $ctx($thies, [
                'report_title' => $report->title,
                'progress' => $report->progress_percentage,
            ]), 6, false);
        }

        // --- Promoteur ---
        $emit($promoter, NotificationType::SubscriptionActivated,
            ['tier' => SubscriptionTier::Premium->label(), 'url' => '/promoteur/abonnement'], 25, true);
        $emit($promoter, NotificationType::ProjectFunded, $ctx($thies), 120, true);
        $emit($promoter, NotificationType::InvestmentReceived,
            $ctx($almadies, ['amount' => 2_500_000]), 90, false);

        // --- Second promoteur : résiliation du Premium, puis Essentiel ---
        // Mêmes dates que ses contrats, sinon sa boîte contredit son historique.
        $emit($secondPromoter, NotificationType::SubscriptionCancelled,
            ['tier' => SubscriptionTier::Premium->label(), 'url' => '/promoteur/abonnement'], 60, true);
        $emit($secondPromoter, NotificationType::SubscriptionActivated,
            ['tier' => SubscriptionTier::Basic->label(), 'url' => '/promoteur/abonnement'], 10, false);
    }

    /**
     * Dossier d'opération validé (niveau 3, §7.2) : les pièces propres à un
     * projet, refaites à chaque opération. Le titre foncier qu'il contient est
     * une variable du scoring — un projet dont l'assise foncière est vérifiée
     * inspire davantage confiance.
     */
    private function seedProjectDossier(Project $project, User $promoter, User $admin): void
    {
        // Toutes les pièces attendues pour ce sous-type de promoteur : c'est
        // ce dossier-là que `ProjectController::publish` exige, un titre
        // foncier seul ne suffirait plus à mettre un projet en financement.
        foreach ($promoter->promoterProfile()->requiredProjectDocuments() as $type) {
            $this->seedApprovedDocument($promoter, $admin, $type, $project);
        }
    }

    /**
     * Calcule les scores IA via le service Python (§8).
     * Le service peut ne pas tourner (installation fraîche, machine sans
     * Python) : dans ce cas on n'invente pas de score, on signale la marche à
     * suivre. Les projets s'affichent simplement sans analyse.
     */
    private function scoreProjects(): void
    {
        $ai = app(AiScoringClient::class);

        if (! $ai->isEnabled() || $ai->health() === null) {
            $this->command?->warn(
                'Service de scoring IA injoignable : projets seedés sans score. '
                ."Démarrez-le (cd ai-service && ./run.sh) puis lancez « php artisan projects:score »."
            );

            return;
        }

        $scored = 0;

        foreach (Project::with('promoter')->get() as $project) {
            if ($ai->scoreProject($project) !== null) {
                $scored++;
            }
        }

        $this->command?->info("Scoring IA : {$scored} projet(s) analysés.");
    }

    /**
     * Dossier KYC complet et validé pour un compte de démonstration : une pièce
     * approuvée par type exigé, avec un fichier factice sur le disque privé
     * (pour que le téléchargement fonctionne dans l'espace de modération).
     */
    private function seedApprovedKyc(User $user, User $reviewer): void
    {
        // La liste dépend du sous-type pour un promoteur (particulier ou
        // société), du rôle pour les autres : `User::requiredKycDocuments()`
        // arbitre, ne pas repasser par le rôle seul.
        foreach ($user->requiredKycDocuments() as $type) {
            $this->seedApprovedDocument($user, $reviewer, $type);
        }
    }

    /**
     * Une pièce de démonstration validée.
     *
     * Les pièces datées sont émises récemment : seedées trop anciennes, elles
     * seraient périmées dès le lendemain du seed et feraient retomber les
     * comptes de démo en « non vérifié ».
     */
    private function seedApprovedDocument(
        User $user,
        User $reviewer,
        DocumentType $type,
        ?Project $project = null,
    ): void {
        $name = Str::uuid()->toString().'.pdf';
        $path = "kyc/{$user->id}/{$name}";

        Storage::disk('local')->put(
            $path,
            "%PDF-1.4\n% Pièce de démonstration — {$type->label()} — {$user->name}\n"
        );

        $issuedAt = $type->validityMonths() !== null ? now()->subDays(20)->toDateString() : null;

        VerificationDocument::create([
            'user_id' => $user->id,
            'project_id' => $project?->id,
            'context' => $project
                ? VerificationContext::ProjectVerification->value
                : $user->role->kycContext()->value,
            'type' => $type->value,
            'file_path' => $path,
            'original_name' => Str::slug($type->label()).'.pdf',
            'issued_at' => $issuedAt,
            'expires_at' => VerificationDocument::expiryFor($type, $issuedAt),
            'status' => DocumentStatus::Approved->value,
            'reviewed_by' => $reviewer->id,
            'review_note' => null,
            'reviewed_at' => now()->subDays(2),
        ]);
    }
}
