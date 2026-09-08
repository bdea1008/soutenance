"""
Variables d'entrée du scoring et références marché (§8.1).

Ce module est le contrat entre Laravel et le service : l'ordre des colonnes
produites par `encode()` est celui attendu par les modèles entraînés. Toute
modification impose de ré-entraîner (voir train.py).
"""

from __future__ import annotations

from dataclasses import dataclass, field

import numpy as np

MODEL_VERSION = "andtabbax-scoring-1.0"

# --- Nomenclature ---------------------------------------------------------

REGIONS = ["Dakar", "Thiès", "Saint-Louis", "Diourbel", "Ziguinchor", "Autre"]
CATEGORIES = ["résidentiel", "commercial", "terrain", "mixte"]

# Attractivité relative des régions : profondeur du marché, liquidité à la
# revente, pression démographique. 1.0 = référence Dakar.
REGION_ATTRACTIVENESS = {
    "Dakar": 1.00,
    "Thiès": 0.82,
    "Saint-Louis": 0.68,
    "Diourbel": 0.60,
    "Ziguinchor": 0.55,
    "Autre": 0.50,
}

# Rendement annuel observé par catégorie (%), et volatilité associée.
# Le terrain rapporte davantage mais reste le plus spéculatif.
MARKET_RETURN = {
    "résidentiel": {"mean": 13.0, "volatility": 3.0},
    "commercial": {"mean": 14.5, "volatility": 4.0},
    "terrain": {"mean": 18.0, "volatility": 7.0},
    "mixte": {"mean": 14.0, "volatility": 4.5},
}

# Durée habituelle d'une opération, en mois.
TYPICAL_DURATION = {
    "résidentiel": (12, 30),
    "commercial": (12, 36),
    "terrain": (6, 18),
    "mixte": (12, 33),
}

# Budget habituel d'une opération, en FCFA.
TYPICAL_BUDGET = {
    "résidentiel": (30_000_000, 400_000_000),
    "commercial": (40_000_000, 800_000_000),
    "terrain": (15_000_000, 200_000_000),
    "mixte": (40_000_000, 600_000_000),
}


def normalize_region(value: str | None) -> str:
    return value if value in REGION_ATTRACTIVENESS else "Autre"


def normalize_category(value: str | None) -> str:
    if value is None:
        return "résidentiel"
    lowered = value.strip().lower()
    return lowered if lowered in MARKET_RETURN else "résidentiel"


def market_return(category: str) -> float:
    """Rendement de référence du marché pour cette catégorie."""
    return MARKET_RETURN[normalize_category(category)]["mean"]


@dataclass
class ProjectFeatures:
    """Caractéristiques d'un projet soumises au scoring."""

    region: str = "Autre"
    category: str = "résidentiel"
    funding_goal: float = 0.0
    expected_return_rate: float = 0.0
    duration_months: int = 12
    min_investment: float = 0.0

    # Historique du promoteur (§8.1) : c'est le signal de confiance le plus fort.
    promoter_completed_projects: int = 0
    promoter_active_projects: int = 0
    promoter_months_active: int = 0
    promoter_kyc_verified: bool = False

    # Pièces du projet validées (titre foncier, permis de construire — §7.2).
    project_documents_verified: bool = False

    def normalized(self) -> "ProjectFeatures":
        return ProjectFeatures(
            region=normalize_region(self.region),
            category=normalize_category(self.category),
            funding_goal=max(0.0, float(self.funding_goal or 0)),
            expected_return_rate=max(0.0, float(self.expected_return_rate or 0)),
            duration_months=max(1, int(self.duration_months or 1)),
            min_investment=max(0.0, float(self.min_investment or 0)),
            promoter_completed_projects=max(0, int(self.promoter_completed_projects or 0)),
            promoter_active_projects=max(0, int(self.promoter_active_projects or 0)),
            promoter_months_active=max(0, int(self.promoter_months_active or 0)),
            promoter_kyc_verified=bool(self.promoter_kyc_verified),
            project_documents_verified=bool(self.project_documents_verified),
        )


# --- Variables dérivées ---------------------------------------------------

def over_promise(features: ProjectFeatures) -> float:
    """
    Écart entre le rendement promis et le marché, en points.
    Positif = le promoteur promet plus que ce que le marché rend. C'est le
    principal signal d'alerte sur une plateforme d'investissement.
    """
    return features.expected_return_rate - market_return(features.category)


def duration_fit(features: ProjectFeatures) -> float:
    """
    0 si la durée annoncée est dans la fourchette habituelle ; négatif si elle
    est trop courte (délai intenable), positif si anormalement longue.
    """
    low, high = TYPICAL_DURATION[features.category]
    if features.duration_months < low:
        return (features.duration_months - low) / low
    if features.duration_months > high:
        return (features.duration_months - high) / high
    return 0.0


def budget_fit(features: ProjectFeatures) -> float:
    """
    Cohérence du budget avec la catégorie et la profondeur du marché local.
    Un budget hors normes pour la région est un facteur de risque.
    """
    low, high = TYPICAL_BUDGET[features.category]
    ceiling = high * REGION_ATTRACTIVENESS[features.region]
    if features.funding_goal < low:
        return (features.funding_goal - low) / max(low, 1)
    if features.funding_goal > ceiling:
        return (features.funding_goal - ceiling) / max(ceiling, 1)
    return 0.0


def ticket_ratio(features: ProjectFeatures) -> float:
    """Poids du ticket minimum dans l'opération : mesure l'ouverture aux petits porteurs."""
    if features.funding_goal <= 0:
        return 0.0
    return features.min_investment / features.funding_goal


FEATURE_NAMES: list[str] = [
    *[f"region={r}" for r in REGIONS],
    *[f"category={c}" for c in CATEGORIES],
    "log_funding_goal",
    "expected_return_rate",
    "over_promise",
    "duration_months",
    "duration_fit",
    "budget_fit",
    "ticket_ratio",
    "region_attractiveness",
    "promoter_completed_projects",
    "promoter_active_projects",
    "promoter_months_active",
    "promoter_kyc_verified",
    "project_documents_verified",
]


def encode(features: ProjectFeatures) -> np.ndarray:
    """Vecteur de variables, dans l'ordre de FEATURE_NAMES."""
    f = features.normalized()

    row: list[float] = []
    row += [1.0 if f.region == r else 0.0 for r in REGIONS]
    row += [1.0 if f.category == c else 0.0 for c in CATEGORIES]
    row += [
        float(np.log10(max(f.funding_goal, 1.0))),
        f.expected_return_rate,
        over_promise(f),
        float(f.duration_months),
        duration_fit(f),
        budget_fit(f),
        ticket_ratio(f),
        REGION_ATTRACTIVENESS[f.region],
        float(f.promoter_completed_projects),
        float(f.promoter_active_projects),
        float(f.promoter_months_active),
        1.0 if f.promoter_kyc_verified else 0.0,
        1.0 if f.project_documents_verified else 0.0,
    ]

    return np.asarray(row, dtype=np.float64)


def encode_many(records: list[ProjectFeatures]) -> np.ndarray:
    return np.vstack([encode(r) for r in records]) if records else np.empty((0, len(FEATURE_NAMES)))
