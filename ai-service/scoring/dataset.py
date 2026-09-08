"""
Jeu d'entraînement synthétique.

La plateforme n'a pas encore d'historique de projets livrés : on ne peut donc
pas entraîner sur du réel. On simule ici des opérations immobilières à partir
d'hypothèses de marché explicites (voir features.py), on en dérive une issue
« observée » (rendement réellement servi, délai réel, incident), puis on
entraîne le modèle sur ces couples.

Ce que le modèle apprend est donc la structure de ces hypothèses, pas une
vérité de terrain. C'est assumé pour le MVP : dès que la plateforme aura des
projets livrés, `train.py` doit être ré-exécuté sur l'historique réel — le
reste de la chaîne (variables, service, intégration Laravel) ne change pas.
"""

from __future__ import annotations

import numpy as np

from .features import (
    CATEGORIES,
    MARKET_RETURN,
    REGION_ATTRACTIVENESS,
    REGIONS,
    TYPICAL_BUDGET,
    TYPICAL_DURATION,
    ProjectFeatures,
    budget_fit,
    duration_fit,
    encode_many,
    market_return,
    over_promise,
)

# Le marché sénégalais est très concentré sur Dakar.
REGION_WEIGHTS = [0.48, 0.18, 0.12, 0.09, 0.08, 0.05]
CATEGORY_WEIGHTS = [0.42, 0.28, 0.22, 0.08]

# Bornes de découpage de l'indice de risque. Calées pour donner une répartition
# plausible sur le marché simulé (~30 % faible, ~45 % modéré, ~25 % élevé) :
# une plateforme qui classerait la majorité de ses projets « faible risque »
# ne rendrait aucun service à ses investisseurs.
RISK_THRESHOLDS = (0.285, 0.465)


def _sample_project(rng: np.random.Generator) -> ProjectFeatures:
    region = str(rng.choice(REGIONS, p=REGION_WEIGHTS))
    category = str(rng.choice(CATEGORIES, p=CATEGORY_WEIGHTS))

    low, high = TYPICAL_BUDGET[category]
    # Log-uniforme dans la fourchette, avec une minorité d'opérations hors normes.
    goal = float(10 ** rng.uniform(np.log10(low), np.log10(high)))
    if rng.random() < 0.12:
        goal *= float(rng.uniform(1.6, 4.0))  # projet surdimensionné
    if rng.random() < 0.06:
        goal *= float(rng.uniform(0.2, 0.5))  # opération anormalement petite

    d_low, d_high = TYPICAL_DURATION[category]
    duration = int(rng.integers(d_low, d_high + 1))
    if rng.random() < 0.15:
        duration = max(3, int(duration * rng.uniform(0.3, 0.7)))  # délai intenable

    # Rendement annoncé : autour du marché, avec ~22 % de sur-promesses.
    base = MARKET_RETURN[category]
    promised = float(rng.normal(base["mean"], base["volatility"] * 0.5))
    if rng.random() < 0.22:
        promised += float(rng.uniform(4.0, 16.0))
    promised = float(np.clip(promised, 3.0, 45.0))

    # Ticket minimum : entre 0,1 % et 3 % de l'objectif.
    min_investment = goal * float(rng.uniform(0.001, 0.03))

    completed = int(rng.poisson(1.4))
    active = int(rng.poisson(1.0))
    months_active = int(min(120, rng.exponential(20) + completed * 8))

    return ProjectFeatures(
        region=region,
        category=category,
        funding_goal=goal,
        expected_return_rate=promised,
        duration_months=duration,
        min_investment=min_investment,
        promoter_completed_projects=completed,
        promoter_active_projects=active,
        promoter_months_active=months_active,
        promoter_kyc_verified=bool(rng.random() < 0.88),
        project_documents_verified=bool(rng.random() < 0.62),
    )


def _track_record_index(f: ProjectFeatures) -> float:
    """Solidité du promoteur, ramenée sur 0..1 (saturation au-delà de 5 projets livrés)."""
    livraisons = min(f.promoter_completed_projects, 5) / 5.0
    anciennete = min(f.promoter_months_active, 60) / 60.0
    # Un promoteur qui mène trop d'opérations de front se disperse.
    surcharge = max(0.0, (f.promoter_active_projects - 3)) * 0.08

    return float(np.clip(0.6 * livraisons + 0.4 * anciennete - surcharge, 0.0, 1.0))


def _over_promise_index(f: ProjectFeatures) -> float:
    """Sur-promesse ramenée sur 0..1+ (10 points au-dessus du marché ≈ 1)."""
    return max(0.0, over_promise(f)) / 10.0


def _outcome(f: ProjectFeatures, rng: np.random.Generator) -> dict[str, float | str]:
    """Issue simulée d'une opération, d'où sont tirées les cibles d'apprentissage."""
    track = _track_record_index(f)
    excess = _over_promise_index(f)
    d_fit = duration_fit(f)
    b_fit = budget_fit(f)
    attractiveness = REGION_ATTRACTIVENESS[f.region]

    # --- Score de confiance ------------------------------------------------
    quality = (
        52.0
        + 16.0 * track
        + 7.0 * (1.0 if f.project_documents_verified else 0.0)
        + 5.0 * (1.0 if f.promoter_kyc_verified else 0.0)
        + 12.0 * (attractiveness - 0.7)
        - 17.0 * excess
        # Un délai trop court pénalise plus qu'un délai confortable.
        - 20.0 * abs(min(d_fit, 0.0))
        - 4.0 * max(d_fit, 0.0)
        - 9.0 * abs(b_fit)
        + float(rng.normal(0, 5.0))
    )
    confidence = float(np.clip(quality, 1.0, 99.0))

    # --- Rendement réellement servi ---------------------------------------
    market = market_return(f.category)
    volatility = MARKET_RETURN[f.category]["volatility"]

    # Ce que l'opération est capable de générer, indépendamment de ce qui est
    # annoncé : le marché local, corrigé par la solidité du promoteur.
    capacity = (
        market * (0.9 + 0.2 * attractiveness) * (0.80 + 0.35 * track)
        - 3.0 * excess
        - 2.5 * abs(min(d_fit, 0.0))
        - 1.5 * abs(b_fit)
        + float(rng.normal(0, volatility * 0.55))
    )

    # En co-investissement, l'investisseur touche au mieux le taux contractuel :
    # le potentiel au-delà revient au promoteur, mais la sous-performance, elle,
    # est intégralement subie. Le réalisé est donc plafonné par la promesse.
    realized = float(np.clip(min(f.expected_return_rate, capacity), -12.0, 40.0))

    # --- Délai de retour ---------------------------------------------------
    # Le capital revient à la sortie de l'opération : le délai réel dérive du
    # délai annoncé, allongé par les retards des dossiers fragiles.
    delay = 0.05 + 0.45 * (1.0 - confidence / 100.0) + 0.25 * abs(min(d_fit, 0.0))
    payback = f.duration_months * (1.0 + delay) * float(rng.normal(1.0, 0.08))
    payback = float(np.clip(payback, 3.0, 96.0))

    # --- Niveau de risque --------------------------------------------------
    # Le risque n'est pas le simple complément du score de confiance : un
    # promoteur sans antécédent ou un projet sans pièces vérifiées porte un
    # risque d'exécution propre, même quand le dossier paraît par ailleurs bon.
    risk_index = (
        0.35 * (1.0 - confidence / 100.0)
        + 0.25 * min(excess, 1.5)
        + 0.12 * (1.0 - track)
        + 0.15 * abs(min(d_fit, 0.0))
        + 0.10 * (volatility / 7.0)
        + 0.08 * abs(b_fit)
        + 0.06 * (0.0 if f.project_documents_verified else 1.0)
        + 0.05 * (0.0 if f.promoter_kyc_verified else 1.0)
        + float(rng.normal(0, 0.04))
    )
    if risk_index < RISK_THRESHOLDS[0]:
        risk = "low"
    elif risk_index < RISK_THRESHOLDS[1]:
        risk = "medium"
    else:
        risk = "high"

    return {"confidence": confidence, "roi": realized, "payback": payback, "risk": risk}


def build(n_samples: int = 12_000, seed: int = 42) -> dict[str, np.ndarray]:
    """Génère le jeu d'entraînement encodé."""
    rng = np.random.default_rng(seed)

    projects = [_sample_project(rng) for _ in range(n_samples)]
    outcomes = [_outcome(p, rng) for p in projects]

    return {
        "X": encode_many(projects),
        "confidence": np.array([o["confidence"] for o in outcomes], dtype=np.float64),
        "roi": np.array([o["roi"] for o in outcomes], dtype=np.float64),
        "payback": np.array([o["payback"] for o in outcomes], dtype=np.float64),
        "risk": np.array([o["risk"] for o in outcomes], dtype=object),
    }
