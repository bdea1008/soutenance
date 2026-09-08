# AndTabbax

**Plateforme intelligente de co-investissement immobilier — contexte sénégalais.**
*« Investir ensemble. Construire ensemble. »*

Voir le descriptif complet : [`andtabbax_descriptif_projet.md`](andtabbax_descriptif_projet.md).

---

## Architecture

Monolithe **Laravel modulaire** (choix MVP), organisé par domaine métier — décomposable
en microservices ultérieurement (cf. §11 du descriptif).

| Couche | Techno | Statut |
|---|---|---|
| Backend API | Laravel 13 (PHP 8.4) | 🟢 Auth + Projets + Abonnements + KYC |
| Base de données | MariaDB / MySQL (cible) · SQLite (dev local) | 🟢 Schéma (10 entités §12) |
| Authentification | JWT (`php-open-source-saver/jwt-auth`) | 🟢 register / login / me / refresh / logout |
| Projets & investissement | Laravel | 🟢 Aperçu public, détail, publication, investissement simulé |
| Abonnements promoteur | Laravel | 🟢 Paliers, quotas, paiements simulés |
| Documents / KYC | Laravel + stockage privé | 🟢 Dépôt, checklist par rôle, modération admin |
| Suivi de chantier | Laravel + stockage privé | 🟢 Journal d'avancement, photos, progression |
| Frontend web | React (Vite) | 🟢 Parcours visiteur / investisseur / promoteur / admin |
| IA / Data | Python + Scikit-learn (service séparé) | 🟢 Scoring des projets, facteurs explicatifs |
| Notifications | Laravel | 🟢 Canal application ; SMS/WhatsApp à venir |
| Emails transactionnels | Laravel (Mailable + Blade) | 🟢 Mot de passe oublié — **envoi simulé**, boîte consultable |
| Tableau de bord analytique | Laravel + React (SVG) | 🟢 Trois lectures : investisseur, promoteur, admin |
| Back-office administrateur | Laravel + React | 🟢 Console, comptes, projets, pièces KYC, finances |
| Mobile | Flutter | ⚪ Plus tard |

Arborescence :

```
andTabbaxProject/
├── andtabbax_descriptif_projet.md   # Cahier des charges
├── backend/                          # API Laravel
├── frontend/                         # Application React (Vite)
├── ai-service/                       # Service de scoring Python (voir son README)
└── README.md
```

## Modèle d'accès (3 niveaux, §6)

1. **Visiteur** (public) — aperçu projets, statistiques, à propos.
2. **Authentifié** (JWT) — détail d'un projet, initier l'ajout d'un projet.
3. **Vérifié KYC** — investir effectivement · publier (promoteur, abonnement actif requis).

Middlewares correspondants : `auth:api`, `role:...`, `kyc.verified`.

### Vérification KYC (§7)

Le statut `kyc_status` d'un utilisateur n'est jamais posé à la main : il **découle
des pièces déposées**, recalculé (`User::recomputeKycStatus()`) à chaque dépôt,
retrait ou décision d'administrateur.

| Rôle | Pièces exigées |
|---|---|
| Investisseur | pièce d'identité + justificatif de domicile |
| Promoteur | pièce d'identité + registre de commerce |
| Administrateur | aucune (non soumis au KYC) |

Pour un même type de pièce, la meilleure issue l'emporte (validé > en attente >
rejeté) : redéposer après un rejet remet donc la pièce en attente d'examen.
Les fichiers sont stockés hors du dossier public (`storage/app/private/kyc/{user}/`)
sous un nom neutre (UUID) et ne sont servis que par la route de téléchargement
authentifiée, réservée au propriétaire et aux administrateurs.
Formats acceptés : JPG, PNG, PDF — 5 Mo maximum.

### Suivi de chantier (§7.5)

Le promoteur publie des rapports d'avancement sur **son** projet ; tout utilisateur
authentifié peut les consulter — la transparence du chantier est ce qui met en
confiance avant d'investir.

- Le suivi ne s'ouvre qu'une fois la collecte bouclée (`funded`, `in_progress`,
  `completed`) : avant, il n'y a pas de chantier. Le premier rapport fait
  automatiquement passer le projet de « financé » à « en chantier ».
- **Un chantier ne recule pas** : l'avancement d'un nouveau rapport ne peut pas
  être inférieur à celui du rapport précédent, et un rapport ne peut pas être
  daté du futur.
- Photos stockées comme les pièces KYC, hors du dossier public
  (`storage/app/private/reports/{project}/`), servies une par une par une route
  authentifiée. JPG/PNG/WEBP, 6 photos par rapport, 5 Mo chacune.
- Le détail d'un projet expose l'avancement courant (`construction`) et le
  nombre de rapports.

### Paiement de l'investissement (§2)

« Investir dans un projet » inclut « Effectuer un paiement » (diagramme de cas
d'utilisation) : investir crée désormais un `Payment` (`purpose=contribution`)
et sa `Transaction`, dans la même transaction DB que la `Contribution` — même
mécanique que l'abonnement promoteur, référence `INV-…`. L'investisseur choisit
son moyen de paiement (Wave/Orange Money) comme pour un abonnement. **Toujours
sans frais** (§16.2.b) : le paiement transfère exactement le montant investi,
aucune commission ni majoration — seule sa traçabilité devient réelle. Le
journal de paiements de l'administration (`/admin/finances`) mêle donc
désormais deux motifs, filtrables (`?purpose=subscription|contribution`) ; le
revenu affiché reste strictement celui des abonnements, non affecté.

### Moyens de paiement (§7.4)

Trois moyens, deux façons de s'identifier — c'est `PaymentProvider::kind()` qui
porte la distinction, et le reste du code s'y réfère plutôt que d'énumérer les
cas :

| Moyen | Famille | Ce qui est demandé |
|---|---|---|
| Wave | `mobile_money` | numéro à débiter — celui du compte par défaut, ou un autre |
| Orange Money | `mobile_money` | idem |
| Carte bancaire | `card` | numéro, date d'expiration, CVC, nom du porteur |

Les règles vivent dans un seul trait (`ValidatesPaymentInstrument`), partagé
par l'investissement, la souscription et le renouvellement : les trois
encaissements de la plateforme exigent exactement les mêmes coordonnées. Le
numéro de carte est contrôlé par la **clé de Luhn** (`App\Rules\CardNumber`) et
l'expiration ramenée à la **fin du mois** (`App\Rules\CardExpiry`) — une carte
en 03/26 reste valable tout le mois de mars.

**Ce qui est conservé, et rien d'autre** (`App\Support\PaymentInstrument`, seul
endroit qui en décide) :

- Mobile Money → `payments.payer_phone`, le numéro effectivement débité ;
- Carte → `payments.card_brand` et `payments.card_last4`.

Le **numéro de carte complet, le CVC et la date d'expiration ne sont
jamais écrits en base** : ils ne franchissent la requête que le temps d'être
validés. Personne n'a le droit de les stocker sans certification PCI-DSS, et le
fait que les paiements soient simulés n'est pas une raison de prendre
l'habitude inverse. Ils figurent aussi dans `dontFlash` (bootstrap/app.php) pour
qu'aucun rapport d'exception ne les recopie. Quand un vrai prestataire sera
branché, il rendra un jeton, qui viendra se ranger à côté de ces colonnes.

### Avis d'investisseurs (§2)

Noter et commenter un projet (1 à 5, commentaire facultatif) — extension
d'« investir dans un projet » du diagramme de cas d'utilisation : réservé à
qui a **réellement investi**, vérifié en contrôleur (`ContributionStatus::Confirmed`),
pas seulement au rôle investisseur. Une seule note par investisseur et par
projet (`updateOrCreate`) : republier revient à la corriger, pas à l'empiler.
Le promoteur est notifié au premier dépôt, jamais sur une simple correction.
La fiche projet expose la moyenne et le nombre d'avis (`reviews_summary`).

### Scoring IA (§8)

Le service Python `ai-service/` note chaque projet (score de confiance, rendement
réellement attendu, délai de retour, niveau de risque, facteurs explicatifs).
Voir [`ai-service/README.md`](ai-service/README.md) pour le modèle et les données.

```bash
cd ai-service && ./run.sh                 # démarre le service sur :8001
cd backend && php artisan projects:score  # (re)calcule les scores
```

- Le score est recalculé **automatiquement à la publication** d'un projet, et à
  la demande via `php artisan projects:score [--all] [--id=…]`.
- **Le backend ne dépend pas du service** : s'il est arrêté, la publication
  aboutit quand même et le dernier score connu reste affiché. Configuration :
  `AI_SCORING_URL`, `AI_SCORING_TIMEOUT`, `AI_SCORING_ENABLED`.
- L'historique des scores est conservé (`ai_scores`) ; `latestScore` remonte le
  plus récent.

### Back-office administrateur (§5)

Huit écrans sous `/admin`, avec une navigation secondaire propre (`AdminNav`) :
**Console** (`/admin`), **Utilisateurs** (`/admin/utilisateurs`), **Projets**
(`/admin/projets`), **Pièces KYC** (`/admin/documents`), **Abonnements**
(`/admin/abonnements`), **Finances** (`/admin/finances`), **Messages**
(`/admin/messages`), **Analyses** (`/admin/analyses`).

La console ne répond qu'à une question — *qu'est-ce qui attend une décision ?* —
et chaque file mène en un clic à l'écran qui la traite. Chaque compteur n'a
qu'un seul point d'affichage : la page Analyses ne montre que des tendances et
des répartitions, jamais des chiffres déjà portés ailleurs. Même partage entre
**Abonnements** (le parc de contrats : qui est abonné à quoi, jusqu'à quand) et
**Finances** (l'argent encaissé : revenus, paliers, journal des paiements).

**L'administrateur n'a pas d'espace personnel.** Il n'a ni portefeuille, ni
projets, ni abonnement : le back-office lui tient lieu de tableau de bord, et
`/tableau-de-bord`, `/verification` et `/promoteur/*` lui sont fermés (il y est
redirigé vers `/admin`). Sa connexion le dépose directement sur `/admin`, et les
entrées « Projets » et « Abonnements » de la barre principale le mènent aux vues
« plateforme entière » plutôt qu'au catalogue public et à la grille tarifaire. Symétriquement côté API, il est retiré des groupes de
routes promoteur et investisseur — **il ne peut ni créer, publier, modifier ou
supprimer un projet, ni souscrire un abonnement, ni investir**. Il agit sur les
projets uniquement par `POST /admin/projects/{project}/moderate`, qui trace la
décision, la motive et notifie le promoteur. Être juge et partie est le seul
risque qu'un back-office de supervision ne peut pas se permettre.

Décisions structurantes :

- **La suspension d'un compte prend effet immédiatement**, session en cours
  comprise : le middleware `account.active` revalide à chaque requête du niveau 2,
  car un JWT déjà émis resterait sinon valide jusqu'à son expiration. `auth/me` et
  `auth/logout` en sont exclus, pour que l'intéressé puisse constater son état et
  fermer sa session. Côté React, un `403 account_suspended` ferme la session et
  renvoie vers `/connexion?suspendu=1`, qui explique le refus.
- **Un retrait de projet n'est jamais une suppression** : le projet passe au statut
  « annulé » et disparaît du catalogue public, mais reste dans l'historique des
  investisseurs. Le rétablissement le ramène en brouillon — ou directement en
  « publié » s'il a déjà collecté des fonds, car il ne peut alors plus redevenir
  librement modifiable. Un projet livré ne peut plus être retiré.
- **Un administrateur ne peut ni se désactiver, ni se déclasser, ni se
  supprimer lui-même.** C'est suffisant pour garantir qu'il reste toujours un
  administrateur actif : seul un administrateur actif atteint ces routes, donc
  l'auteur d'une décision reste administrateur après celle-ci.
- **La suppression d'un compte est douce, jamais réelle en base**
  (`POST/DELETE /admin/users`) : les clés étrangères vers `users` sont en
  cascade, une suppression réelle effacerait le catalogue et l'historique
  financier du compte visé. Le compte disparaît de l'annuaire et ne peut plus
  se connecter (`SoftDeletes` retire les comptes supprimés des requêtes et de
  la résolution du JWT), mais projets, contributions et paiements restent
  attribués à son nom — mêmes relations `belongsTo` en `withTrashed()` que
  pour un projet retiré, jamais une suppression.
- **Le rôle « juridique & conformité »** (`UserRole::Legal`) n'est accessible
  qu'à la création par un administrateur — jamais un choix de l'inscription
  publique. Il consulte tous les projets, tous statuts confondus, sur
  `/verification-legale` (`GET /admin/projects[/stats]`, ouvert aux rôles
  `admin,legal`), mais n'a aucun droit de décision : `POST
  /admin/projects/{id}/moderate` reste strictement `role:admin`. Ni KYC ni
  espace personnel, comme l'administrateur.
- **Un changement de rôle recalcule le KYC** : les pièces exigées diffèrent selon
  le rôle (§7.1), un promoteur devenu investisseur ne doit pas rester « vérifié »
  sans son justificatif de domicile.
- **Revenu et collecte ne sont jamais additionnés** : le chiffre d'affaires, ce
  sont les abonnements promoteur ; les montants investis transitent par la
  plateforme sans lui appartenir (investir est sans frais). L'écran Finances les
  présente séparément, avec la mention « simulé » visible partout (§16.5).
- Les décisions notifient le titulaire (§7.7) et **un rejet doit être motivé** :
  la notification reprend ce motif, c'est la seule explication qu'il recevra.
- **La collecte n'a qu'une source** : `projects.amount_raised`, jamais la somme
  des contributions. Les deux divergent dès qu'une collecte est reprise d'un
  historique hors plateforme, et deux chiffres différents pour la même notion
  dans le même back-office sont pires que pas de chiffre du tout.

### Tableau de bord analytique (§7.6)

`GET /api/me/analytics` renvoie une charge utile **façonnée par le rôle** :
l'investisseur suit son portefeuille (capital, rendement pondéré, répartition,
positions), le promoteur sa collecte (taux de financement, collecte par projet,
avancement des chantiers), l'administrateur la plateforme (collecte, revenus
d'abonnement, entonnoir KYC, répartition par région).

- Les agrégations mensuelles sont faites **en PHP, pas en SQL** : `strftime`
  (SQLite) et `DATE_FORMAT` (MySQL) ne sont pas interchangeables et le projet
  doit tourner sur les deux.
- Les courbes cumulées intègrent ce qui précède la fenêtre de douze mois, sinon
  elles repartiraient artificiellement de zéro.

Les graphiques sont du **SVG écrit à la main** (`src/components/charts/`), sans
librairie : une dépendance de dataviz pèserait plus lourd que l'application
entière, ce qui irait contre l'objectif « connexions limitées » du §14.

Les couleurs de séries (`charts/palette.js`) ne sont pas choisies à l'œil : elles
sont validées contre la surface réelle des cartes (bande de clarté, plancher de
chroma, séparation en vision daltonienne ≥ 8 et en vision normale ≥ 15). Pire
paire adjacente mesurée : ΔE 13,0 en protanopie, 27,2 en vision normale. Deux
teintes passant sous 3:1 de contraste, chaque graphique fournit des **libellés
visibles et une vue tableau** (bouton « Voir les données ») — qui est aussi son
équivalent accessible.

### Notifications applicatives (§7.7)

Toutes les notifications passent par `App\Services\Notifier`, et leur rédaction
vit dans l'enum `App\Enums\NotificationType` : un même événement est donc formulé
de la même façon quel que soit le contrôleur qui le déclenche.

| Événement | Destinataire |
|---|---|
| Pièce KYC validée / rejetée (avec motif) | propriétaire de la pièce |
| Dossier KYC complété | propriétaire |
| Investissement enregistré | investisseur |
| Investissement reçu | promoteur du projet |
| Objectif de collecte atteint | promoteur **et** tous les co-investisseurs |
| Nouveau rapport de chantier | investisseurs du projet (pas l'auteur) |
| Abonnement activé / résilié | promoteur |

- Seul le canal **application** est réellement délivré. SMS, email et WhatsApp
  sont modélisés (`NotificationChannel`) et tracés, mais pas encore émis — même
  logique que les paiements simulés.
- Une notification n'est jamais bloquante : elle est émise après l'opération
  métier, hors transaction.
- Côté interface : cloche avec pastille dans la barre de navigation (compteur
  rafraîchi toutes les 60 s) et boîte de réception complète sur `/notifications`.

### Mot de passe oublié (§10)

Vérification d'identité **par email** : le lien envoyé à l'adresse déclarée du
compte est la seule preuve exigée. Trois routes publiques — celui qui les
emprunte est précisément celui qui ne peut pas se connecter.

| Étape | Route | Écran |
|---|---|---|
| Demander un lien | `POST /api/auth/password/forgot` | `/mot-de-passe-oublie` |
| Vérifier le lien | `GET /api/auth/password/check` | à l'ouverture du formulaire |
| Poser le mot de passe | `POST /api/auth/password/reset` | `/reinitialiser-mot-de-passe` |

Le stockage du jeton repose sur le courtier de réinitialisation de Laravel
(table `password_reset_tokens`) : **jeton haché** en base, **expiration à 60 min**,
**usage unique**, une demande par adresse et par minute.

Trois précautions valent d'être notées :

- **La réponse est identique que l'adresse existe ou non.** Sinon ce point
  d'entrée public dirait qui est inscrit sur la plateforme à partir d'une simple
  liste d'adresses.
- **Un compte désactivé ou supprimé ne reçoit rien** — un compte suspendu ne doit
  pas se redonner un accès en passant par cette porte.
- **Le changement est accusé** : email de confirmation + notification
  applicative. C'est la seule chose qui alerte le titulaire si quelqu'un d'autre
  a réinitialisé son mot de passe ; le message part donc même quand tout est
  légitime.

#### Envoi d'emails simulé

Même logique que les paiements (§16.5) : la mécanique complète est en place,
seule la remise à un serveur de messagerie est remplacée.

`MAIL_MAILER=simulated` branche `App\Mail\Transport\SimulatedTransport`, tout en
bas de la chaîne d'envoi Laravel. Le Mailable est construit, le gabarit Blade est
rendu, les en-têtes sont posés — exactement comme pour un envoi réel — puis le
message est **écrit dans la table `simulated_emails`** au lieu de partir.

| Où le voir | Quoi |
|---|---|
| `/admin/messages` | boîte d'envoi complète (onglet « Messages » du back-office) |
| `/mail-simule/{uuid}` (backend) | le message rendu, tel qu'il aurait été reçu |
| réponse de `password/forgot` | bloc `simulation` : de quoi ouvrir le message sans boîte mail |

Le bloc `simulation` **contient le lien de réinitialisation** : il est coupé par
`MAIL_SIMULATION_REVEAL=false`, à poser en même temps que le passage à un vrai
transport. L'URL de prévisualisation porte l'**UUID** du message et non son
identifiant auto-incrémenté — sinon on parcourrait `/mail-simule/1, 2, 3…` pour
récupérer les liens de tous les comptes.

Passer en envoi réel ne demande **aucun changement de code** : `MAIL_MAILER=smtp`
et les identifiants du serveur. Les Mailable sont de vrais Mailable.

### Identité visuelle

Source unique : `logo.jpeg` à la racine. Les déclinaisons sont générées à partir
d'elle (fond blanc rendu transparent), jamais retouchées à la main :

| Fichier | Usage |
|---|---|
| `frontend/src/assets/logo-mark.png` | médaillon seul — barre de navigation, pied de page, hero, carte projet sans photo, page 404 |
| `frontend/src/assets/logo.png` | logo complet — pages Connexion et Inscription |
| `frontend/public/favicon.png`, `apple-touch-icon.png`, `icon-512.png`, `icon-maskable-512.png` | onglet, écran d'accueil mobile, `site.webmanifest`, aperçu au partage |
| `backend/public/logo.png` | racine du backend (`/`), qui annonce l'API et renvoie vers l'interface |

Deux règles : la **signature « ANDTABBAX » n'est jamais reprise sous ~120 px**
(illisible — le nom est déjà écrit à côté), et le logo étant vert/bleu nuit, il
est posé sur une **pastille claire** (`.logo-chip`) partout où le fond est
sombre, plutôt que d'en produire une variante monochrome.

## Entités (§12)

`users` · `projects` · `contributions` · `payments` · `transactions` ·
`site_reports` · `ai_scores` · `verification_documents` · `promoter_subscriptions` ·
`app_notifications`.

---

## Démarrage avec Docker (recommandé)

Seul prérequis : Docker (avec le greffon Compose). Ni PHP, ni Node, ni Python,
ni base de données à installer sur la machine.

```bash
docker compose up -d --build     # première fois : ~5 min (dont l'entraînement du modèle IA)
```

Puis **http://localhost:8080**. Les comptes de démonstration ci-dessous sont
déjà créés — la base est migrée et amorcée au premier démarrage.

```bash
docker compose logs -f backend   # suivre migration + amorçage
docker compose ps                # état des quatre services
docker compose down              # arrêter, en gardant les données
docker compose down -v           # arrêter et repartir d'une base vierge
```

### Ce que contient la pile

| Service | Image | Rôle |
|---|---|---|
| `web` | nginx + bundle React | Seule porte d'entrée (port 8080) : sert l'interface, relaie `/api` |
| `backend` | PHP 8.4-FPM | API Laravel — jamais exposée directement |
| `ai` | Python 3.12 | Service de scoring FastAPI, joignable seulement par le backend |
| `db` | MariaDB 11.4 | Base de données, jamais exposée sur l'hôte |

Le frontend et l'API partagent la **même origine** (`web` relaie `/api` en
FastCGI) : pas de CORS à configurer, et les chemins relatifs de
`src/api/client.js` fonctionnent tels quels.

Trois points d'attention, tous automatisés par `backend/docker/entrypoint.sh` :

- **`APP_KEY` et `JWT_SECRET`** sont engendrés au premier démarrage et conservés
  dans le volume `backend-storage` — les sessions survivent donc à un
  `docker compose down`, sans qu'aucun secret ne soit versionné. Renseignez-les
  dans `.env` pour un vrai déploiement.
- **L'amorçage ne passe qu'une fois** (le seeder n'est pas idempotent : les
  courriels sont uniques). Pour rejouer : `docker compose down -v`.
- **Le backend attend `ai` en bonne santé** avant de migrer : les projets de
  démonstration sont ainsi notés par le vrai modèle, jamais laissés sans score.

Le modèle de scoring est **entraîné pendant la construction de l'image** (graine
fixe, données simulées) : l'image ne dépend d'aucun fichier `.joblib` local.

Réglages facultatifs — port, mode debug, mot de passe de la base, amorçage :

```bash
cp .env.example .env             # puis ajuster, et relancer `docker compose up -d`
```

---

## Démarrage sans Docker (installation locale)

Prérequis : PHP 8.4, Composer, MariaDB/MySQL.

```bash
# 1. Base de données (une seule fois, nécessite les droits admin MySQL)
sudo mariadb -e "CREATE DATABASE IF NOT EXISTS andtabbax CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; \
  CREATE USER IF NOT EXISTS 'andtabbax'@'localhost' IDENTIFIED BY 'andtabbax'; \
  GRANT ALL PRIVILEGES ON andtabbax.* TO 'andtabbax'@'localhost'; FLUSH PRIVILEGES;"

cd backend

# 2. Migrations + données de démonstration
php artisan migrate --seed

# 3. Lancer l'API
php artisan serve
```

Démarrez `ai-service` **avant** le seed : sinon les projets sont seedés sans
score IA (rattrapable par `php artisan projects:score --all`).

### Comptes de démonstration (après seed)

| Rôle | Email | Mot de passe |
|---|---|---|
| Admin | `admin@andtabbax.sn` | `password` |
| Juridique & conformité | `juridique@andtabbax.sn` | `password` |
| Promoteur | `promoteur@andtabbax.sn` | `password` |
| Promoteur (2) | `promoteur2@andtabbax.sn` | `password` |
| Investisseur | `investisseur@andtabbax.sn` | `password` |

Le second promoteur n'a pas de projet : il porte l'historique d'abonnements
(Premium résilié puis Essentiel en cours) qui rend l'écran `/admin/abonnements`
lisible — le parc seedé compte quatre contrats, actifs, expiré et résilié.

Le compte juridique n'est **jamais auto-attribuable** : c'est un rôle interne,
créé uniquement par un administrateur (`POST /admin/users`, ou l'écran
`/admin/utilisateurs/nouveau`). Il consulte tous les projets en lecture seule
sur `/verification-legale`, tous statuts confondus — la même profondeur que
la supervision administrative, sans aucun droit de décision.

### Entretien

```bash
php artisan storage:prune-orphans --dry-run  # inventaire des fichiers orphelins
php artisan storage:prune-orphans            # suppression après confirmation
```

Un `migrate:fresh --seed` vide les tables sans toucher au disque : les pièces
KYC et les photos de chantier des seeds précédents s'y accumulent, plus servies
par personne (le téléchargement passe toujours par la ligne en base). La
commande les supprime, et signale le cas inverse — une ligne dont le fichier a
disparu — sans y toucher.

### Endpoints

| Méthode | Route | Accès | Niveau |
|---|---|---|---|
| `GET` | `/api/health` | public | — |
| `GET` | `/api/stats` | public | 1 |
| `GET` | `/api/projects` | public (aperçu) | 1 |
| `GET` | `/api/subscription-plans` | public (page Tarifs) | 1 |
| `POST` | `/api/auth/register` | public | — |
| `POST` | `/api/auth/login` | public | — |
| `POST` | `/api/auth/password/forgot` | public (envoi du lien, réponse générique) | — |
| `GET` | `/api/auth/password/check` | public (le lien est-il encore valable ?) | — |
| `POST` | `/api/auth/password/reset` | public (jeton d'email) | — |
| `GET` | `/api/auth/me` | JWT | 2 |
| `POST` | `/api/auth/refresh` | JWT | 2 |
| `POST` | `/api/auth/logout` | JWT | 2 |
| `GET` | `/api/projects/{project}` | JWT | 2 |
| `GET` | `/api/me/contributions` | JWT | 2 |
| `GET` | `/api/me/documents` | JWT (dossier KYC : checklist + pièces) | 2 |
| `POST` | `/api/me/documents` | JWT (dépôt d'une pièce) | 2 |
| `DELETE` | `/api/me/documents/{document}` | JWT (propriétaire, pièce non validée) | 2 |
| `GET` | `/api/documents/{document}/download` | JWT (propriétaire ou `admin`) | 2 |
| `GET` | `/api/me/analytics` | JWT (tableau de bord, forme selon le rôle) | 2 |
| `GET` | `/api/me/notifications` | JWT (boîte de réception, `?unread=1`) | 2 |
| `GET` | `/api/me/notifications/unread` | JWT (compteur pour la pastille) | 2 |
| `POST` | `/api/me/notifications/{id}/read` | JWT (propriétaire) | 2 |
| `POST` | `/api/me/notifications/read-all` | JWT | 2 |
| `DELETE` | `/api/me/notifications/{id}` | JWT (propriétaire) | 2 |
| `GET` | `/api/projects/{project}/reports` | JWT (journal de chantier) | 2 |
| `GET` | `/api/reports/{report}/photos/{index}` | JWT (photo d'un rapport) | 2 |
| `POST` | `/api/projects/{project}/reports` | JWT + `promoter` + KYC (propriétaire, projet financé) | 3 |
| `PUT` | `/api/reports/{report}` | JWT + `promoter` + KYC (propriétaire) | 3 |
| `DELETE` | `/api/reports/{report}` | JWT + `promoter` + KYC (propriétaire) | 3 |
| `GET` | `/api/admin/overview` | JWT + `admin` (console : files d'attente) | 2 |
| `GET` | `/api/admin/documents` | JWT + `admin` (file de modération) | 2 |
| `GET` | `/api/admin/documents/stats` | JWT + `admin` | 2 |
| `POST` | `/api/admin/documents/{document}/review` | JWT + `admin` (valider / rejeter) | 2 |
| `GET` | `/api/admin/users` | JWT + `admin` (annuaire filtrable) | 2 |
| `GET` | `/api/admin/users/stats` | JWT + `admin` | 2 |
| `GET` | `/api/admin/users/{user}` | JWT + `admin` (fiche complète) | 2 |
| `PATCH` | `/api/admin/users/{user}/status` | JWT + `admin` (activer / désactiver, motif requis) | 2 |
| `PATCH` | `/api/admin/users/{user}/role` | JWT + `admin` (change le rôle, recalcule le KYC) | 2 |
| `GET` | `/api/admin/projects` | JWT + `admin` (catalogue, tous statuts) | 2 |
| `GET` | `/api/admin/projects/stats` | JWT + `admin` | 2 |
| `POST` | `/api/admin/projects/{project}/moderate` | JWT + `admin` (`approve` / `cancel` / `restore`) | 2 |
| `GET` | `/api/admin/finance/summary` | JWT + `admin` (revenus, récurrent, parc) | 2 |
| `GET` | `/api/admin/finance/subscriptions` | JWT + `admin` | 2 |
| `GET` | `/api/admin/finance/payments` | JWT + `admin` (historique des transactions) | 2 |
| `GET` | `/api/admin/emails` | JWT + `admin` (boîte d'envoi simulée) | 2 |
| `GET` | `/api/admin/emails/{id}` | JWT + `admin` (corps du message) | 2 |
| `DELETE` | `/api/admin/emails` | JWT + `admin` (vider la boîte) | 2 |
| `GET` | `/api/me/projects` | JWT + `promoter` (brouillons inclus) | 2 |
| `GET` | `/api/me/subscription` | JWT + `promoter` | 2 |
| `GET` | `/api/me/payments` | JWT + `promoter` | 2 |
| `POST` | `/api/subscriptions` | JWT + `promoter` | 3 |
| `POST` | `/api/me/subscription/renew` | JWT + `promoter` | 3 |
| `POST` | `/api/me/subscription/cancel` | JWT + `promoter` | 3 |
| `POST` | `/api/projects` | JWT + `promoter` + KYC | 3 |
| `PUT` | `/api/projects/{project}` | JWT + `promoter` + KYC (propriétaire) | 3 |
| `DELETE` | `/api/projects/{project}` | JWT + `promoter` + KYC (propriétaire) | 3 |
| `POST` | `/api/projects/{project}/publish` | JWT + `promoter` + KYC + **abonnement actif** + quota du palier | 3 |
| `POST` | `/api/projects/{project}/invest` | JWT + `investor` + KYC | 3 |

Exemple :

```bash
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@andtabbax.sn","password":"password"}'
```
