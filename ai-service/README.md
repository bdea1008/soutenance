# AndTabbax — service de scoring IA (§8)

Service Python autonome qui note les projets immobiliers soumis à la plateforme :
score de confiance, rendement réellement attendu, délai de retour, niveau de
risque, et **facteurs explicatifs** en langage métier.

Il est consommé en HTTP par le backend Laravel (`App\Services\AiScoringClient`).
Le service est sans état : il ne stocke rien, Laravel conserve les scores dans
la table `ai_scores`.

---

## Démarrage

```bash
cd ai-service
./run.sh                      # http://127.0.0.1:8001
```

Le script entraîne le modèle automatiquement s'il est absent.

### Installation des dépendances

```bash
pip install --target .libs -r requirements.txt
```

> **Pourquoi `--target .libs` et pas un virtualenv ?** Cette machine n'a pas le
> paquet `python3-venv` (installable par `sudo apt install python3.13-venv`) et
> pip refuse d'écrire dans le Python système (PEP 668). L'installation dans un
> dossier local contourne les deux sans droits administrateur. `run.sh` ajoute
> `.libs/` au `PYTHONPATH`. Sur une machine disposant de `venv`, la procédure
> habituelle fonctionne évidemment aussi.

---

## Endpoints

| Méthode | Route | Rôle |
|---|---|---|
| `GET` | `/health` | état du service, version du modèle, métriques de validation |
| `POST` | `/score` | note un projet |
| `POST` | `/score/batch` | note jusqu'à 200 projets en un appel |

```bash
curl -X POST http://127.0.0.1:8001/score -H 'Content-Type: application/json' -d '{
  "region": "Dakar", "category": "résidentiel",
  "funding_goal": 150000000, "expected_return_rate": 14.5,
  "duration_months": 18, "min_investment": 500000,
  "promoter_completed_projects": 3, "promoter_active_projects": 2,
  "promoter_months_active": 40, "promoter_kyc_verified": true,
  "project_documents_verified": true
}'
```

Réponse : `confidence_score` (0-100), `roi_estimate` (%), `payback_months`,
`risk_level` (`low`/`medium`/`high`), `risk_probabilities`, `factors`,
`model_version`.

---

## Le modèle

Quatre estimateurs `HistGradientBoosting` (scikit-learn) partagent le même
vecteur de variables (`scoring/features.py`) :

| Cible | Type | Tenue sur jeu de test |
|---|---|---|
| Score de confiance | régression | MAE ≈ 4,1 pts · R² ≈ 0,80 |
| Rendement réel | régression | MAE ≈ 1,3 pt · R² ≈ 0,59 |
| Délai de retour | régression | MAE ≈ 1,5 mois · R² ≈ 0,95 |
| Niveau de risque | classification | exactitude ≈ 0,81 |

Variables (§8.1) : région, catégorie, budget, rendement annoncé, durée, ticket
minimum, **écart au rendement de marché**, cohérence budget/durée, et
l'historique du promoteur (opérations livrées, ancienneté, opérations en cours,
KYC vérifié, pièces du projet validées).

### Deux règles métier structurantes

- **La sur-promesse est le principal signal d'alerte.** Un rendement annoncé
  très au-dessus du marché de sa catégorie fait chuter le score et remonter le
  risque, même pour un promoteur solide.
- **Le rendement estimé est plafonné par le taux annoncé.** En co-investissement
  l'investisseur perçoit au mieux le taux contractuel : le surplus revient au
  promoteur, mais la sous-performance est subie en totalité. L'écart entre le
  taux annoncé et le rendement estimé est donc l'indicateur le plus utile de la
  page projet.

### Sur les données d'entraînement

**Le modèle est entraîné sur des données simulées, pas sur du réel** — la
plateforme n'a encore aucun projet livré. `scoring/dataset.py` génère des
opérations à partir d'hypothèses de marché explicites (rendements et durées
observés par catégorie, attractivité des régions, effet de l'historique du
promoteur), en tire une issue « observée », et le modèle apprend cette
structure.

Ce que le modèle capture est donc la cohérence de ces hypothèses, pas une
vérité de terrain. C'est assumé pour le MVP et documenté comme tel dans le code.
Le jour où la plateforme dispose d'un historique de projets livrés, il suffit de
remplacer la source de `dataset.build()` et de relancer `train.py` : variables,
service et intégration Laravel ne bougent pas.

---

## Ré-entraîner

```bash
PYTHONPATH=.libs python3 train.py --samples 20000 --seed 7
```

Obligatoire après toute modification de `scoring/features.py` (l'ordre des
colonnes fait partie du contrat du modèle sérialisé).

---

## Arborescence

```
ai-service/
├── app.py               # API FastAPI
├── train.py             # entraînement → models/scoring.joblib
├── run.sh               # lancement (PYTHONPATH + entraînement si besoin)
├── requirements.txt
├── models/scoring.joblib
└── scoring/
    ├── features.py      # variables et références marché — contrat du modèle
    ├── dataset.py       # génération du jeu d'entraînement simulé
    └── model.py         # entraînement, prédiction, facteurs explicatifs
```
