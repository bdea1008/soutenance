"""
Modèles de scoring : entraînement, chargement, prédiction et explication.

Quatre estimateurs partagent le même vecteur de variables :
  - score de confiance   (régression, 0..100)
  - rendement estimé     (régression, en %)
  - délai de rentabilité (régression, en mois)
  - niveau de risque     (classification : low / medium / high)
"""

from __future__ import annotations

import time
from dataclasses import dataclass
from pathlib import Path

import joblib
import numpy as np
from sklearn.ensemble import HistGradientBoostingClassifier, HistGradientBoostingRegressor
from sklearn.metrics import accuracy_score, mean_absolute_error, r2_score
from sklearn.model_selection import train_test_split

from . import dataset
from .features import (
    FEATURE_NAMES,
    MODEL_VERSION,
    ProjectFeatures,
    budget_fit,
    duration_fit,
    encode,
    market_return,
    over_promise,
)

MODEL_PATH = Path(__file__).resolve().parent.parent / "models" / "scoring.joblib"

RISK_LABELS = {"low": "Faible", "medium": "Modéré", "high": "Élevé"}


@dataclass
class ScoringBundle:
    """Les quatre estimateurs entraînés, plus leurs métriques de validation."""

    confidence: HistGradientBoostingRegressor
    roi: HistGradientBoostingRegressor
    payback: HistGradientBoostingRegressor
    risk: HistGradientBoostingClassifier
    metrics: dict
    version: str = MODEL_VERSION
    feature_names: tuple[str, ...] = tuple(FEATURE_NAMES)


def train(n_samples: int = 12_000, seed: int = 42) -> ScoringBundle:
    """Entraîne les quatre estimateurs et mesure leur tenue sur un jeu de test."""
    data = dataset.build(n_samples=n_samples, seed=seed)
    X = data["X"]

    splits = train_test_split(
        X, data["confidence"], data["roi"], data["payback"], data["risk"],
        test_size=0.2, random_state=seed,
    )
    X_train, X_test = splits[0], splits[1]
    y_conf_tr, y_conf_te = splits[2], splits[3]
    y_roi_tr, y_roi_te = splits[4], splits[5]
    y_pay_tr, y_pay_te = splits[6], splits[7]
    y_risk_tr, y_risk_te = splits[8], splits[9]

    def regressor() -> HistGradientBoostingRegressor:
        return HistGradientBoostingRegressor(
            max_iter=300, learning_rate=0.08, max_depth=6,
            l2_regularization=1.0, random_state=seed,
        )

    confidence = regressor().fit(X_train, y_conf_tr)
    roi = regressor().fit(X_train, y_roi_tr)
    payback = regressor().fit(X_train, y_pay_tr)
    risk = HistGradientBoostingClassifier(
        max_iter=300, learning_rate=0.08, max_depth=6, random_state=seed,
    ).fit(X_train, y_risk_tr)

    metrics = {
        "n_samples": int(n_samples),
        "confidence": {
            "mae": float(mean_absolute_error(y_conf_te, confidence.predict(X_test))),
            "r2": float(r2_score(y_conf_te, confidence.predict(X_test))),
        },
        "roi": {
            "mae": float(mean_absolute_error(y_roi_te, roi.predict(X_test))),
            "r2": float(r2_score(y_roi_te, roi.predict(X_test))),
        },
        "payback": {
            "mae": float(mean_absolute_error(y_pay_te, payback.predict(X_test))),
            "r2": float(r2_score(y_pay_te, payback.predict(X_test))),
        },
        "risk": {"accuracy": float(accuracy_score(y_risk_te, risk.predict(X_test)))},
        "trained_at": time.strftime("%Y-%m-%dT%H:%M:%S%z"),
    }

    return ScoringBundle(confidence=confidence, roi=roi, payback=payback, risk=risk, metrics=metrics)


def save(bundle: ScoringBundle, path: Path = MODEL_PATH) -> Path:
    path.parent.mkdir(parents=True, exist_ok=True)
    joblib.dump(bundle, path)
    return path


def load(path: Path = MODEL_PATH) -> ScoringBundle:
    if not path.exists():
        raise FileNotFoundError(
            f"Modèle introuvable ({path}). Lancez d'abord : python train.py"
        )
    return joblib.load(path)


# --- Prédiction -----------------------------------------------------------

def predict(bundle: ScoringBundle, features: ProjectFeatures) -> dict:
    """Score complet d'un projet, prêt à être stocké dans `ai_scores`."""
    f = features.normalized()
    X = encode(f).reshape(1, -1)

    confidence = float(np.clip(bundle.confidence.predict(X)[0], 0.0, 100.0))
    payback = float(max(1.0, bundle.payback.predict(X)[0]))

    # L'investisseur ne perçoit au mieux que le taux contractuel annoncé : le
    # régresseur peut le dépasser de quelques dixièmes par lissage, on borne.
    roi = float(min(bundle.roi.predict(X)[0], f.expected_return_rate))

    risk = str(bundle.risk.predict(X)[0])
    probabilities = {
        str(label): float(p)
        for label, p in zip(bundle.risk.classes_, bundle.risk.predict_proba(X)[0])
    }

    return {
        "confidence_score": round(confidence, 1),
        "roi_estimate": round(roi, 2),
        "payback_months": int(round(payback)),
        "risk_level": risk,
        "risk_label": RISK_LABELS.get(risk, risk),
        "risk_probabilities": {k: round(v, 3) for k, v in probabilities.items()},
        "factors": explain(f, roi),
        "model_version": bundle.version,
    }


def explain(features: ProjectFeatures, roi_estimate: float) -> list[dict]:
    """
    Facteurs lisibles par un investisseur.

    Ce sont des lectures des variables d'entrée face aux références marché, pas
    une attribution post-hoc du modèle : l'objectif est qu'un investisseur
    comprenne *pourquoi* un projet est bien ou mal noté, en termes métier.
    """
    f = features.normalized()
    factors: list[dict] = []

    # Historique du promoteur.
    livraisons = f.promoter_completed_projects
    if livraisons >= 3:
        factors.append(_factor("Historique du promoteur", "positif",
                               f"{livraisons} projets déjà livrés sur la plateforme."))
    elif livraisons == 0:
        factors.append(_factor("Historique du promoteur", "négatif",
                               "Premier projet : aucun antécédent de livraison."))
    else:
        factors.append(_factor("Historique du promoteur", "neutre",
                               f"{livraisons} projet livré, historique encore court."))

    # Réalisme du rendement annoncé — le signal d'alerte principal.
    ecart = over_promise(f)
    marche = market_return(f.category)
    if ecart > 5:
        factors.append(_factor("Rendement annoncé", "négatif",
                               f"{f.expected_return_rate:.1f} % promis contre "
                               f"{marche:.1f} % observés en {f.category} : promesse difficile à tenir."))
    elif ecart < -3:
        factors.append(_factor("Rendement annoncé", "neutre",
                               f"{f.expected_return_rate:.1f} % promis, en dessous du marché "
                               f"({marche:.1f} %) : prudent."))
    else:
        factors.append(_factor("Rendement annoncé", "positif",
                               f"{f.expected_return_rate:.1f} % promis, cohérent avec le marché "
                               f"({marche:.1f} %)."))

    # Délai annoncé.
    d_fit = duration_fit(f)
    if d_fit < -0.15:
        factors.append(_factor("Délai annoncé", "négatif",
                               f"{f.duration_months} mois : court pour une opération "
                               f"{f.category}, risque de retard."))
    elif d_fit > 0.2:
        factors.append(_factor("Délai annoncé", "neutre",
                               f"{f.duration_months} mois : durée longue, capital immobilisé."))
    else:
        factors.append(_factor("Délai annoncé", "positif",
                               f"{f.duration_months} mois : durée habituelle pour ce type d'opération."))

    # Budget et localisation.
    b_fit = budget_fit(f)
    if abs(b_fit) > 0.35:
        sens = "élevé" if b_fit > 0 else "faible"
        factors.append(_factor("Dimensionnement", "négatif",
                               f"Budget {sens} au regard du marché de {f.region}."))
    else:
        factors.append(_factor("Dimensionnement", "positif",
                               f"Budget cohérent avec le marché de {f.region}."))

    # Pièces justificatives (lien avec le module KYC).
    if f.project_documents_verified:
        factors.append(_factor("Pièces du projet", "positif",
                               "Titre foncier / permis vérifiés par la plateforme."))
    else:
        factors.append(_factor("Pièces du projet", "négatif",
                               "Aucune pièce de projet validée à ce jour."))

    if not f.promoter_kyc_verified:
        factors.append(_factor("Identité du promoteur", "négatif",
                               "Vérification KYC du promoteur non aboutie."))

    # Écart entre promesse et estimation du modèle.
    if f.expected_return_rate - roi_estimate > 4:
        factors.append(_factor("Écart à l'estimation", "négatif",
                               f"Le modèle estime le rendement réel à {roi_estimate:.1f} %, "
                               f"soit {f.expected_return_rate - roi_estimate:.1f} points sous l'annonce."))

    return factors


def _factor(label: str, impact: str, detail: str) -> dict:
    return {"label": label, "impact": impact, "detail": detail}
