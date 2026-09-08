"""
Service de scoring AndTabbax (§8) — API HTTP consommée par le backend Laravel.

    ./run.sh                     # http://127.0.0.1:8001

Le service est sans état : Laravel envoie les caractéristiques d'un projet,
le service renvoie le score. Aucune donnée n'est stockée ici.
"""

from __future__ import annotations

from contextlib import asynccontextmanager

from fastapi import FastAPI, HTTPException
from pydantic import BaseModel, Field

from scoring import model
from scoring.features import MODEL_VERSION, ProjectFeatures

# Chargé une fois au démarrage : le modèle pèse quelques Mo et le chargement
# par requête coûterait plus cher que la prédiction elle-même.
STATE: dict = {"bundle": None, "error": None}


@asynccontextmanager
async def lifespan(_: FastAPI):
    try:
        STATE["bundle"] = model.load()
    except FileNotFoundError as exc:
        # Le service démarre quand même : /health signale le problème, ce qui
        # est plus lisible qu'un crash au lancement.
        STATE["error"] = str(exc)
    yield


app = FastAPI(
    title="AndTabbax — service de scoring",
    version=MODEL_VERSION,
    lifespan=lifespan,
)


class ProjectPayload(BaseModel):
    """Caractéristiques d'un projet envoyées par Laravel."""

    region: str | None = None
    category: str | None = None
    funding_goal: float = Field(0, ge=0)
    expected_return_rate: float = Field(0, ge=0)
    duration_months: int = Field(12, ge=1, le=240)
    min_investment: float = Field(0, ge=0)

    promoter_completed_projects: int = Field(0, ge=0)
    promoter_active_projects: int = Field(0, ge=0)
    promoter_months_active: int = Field(0, ge=0)
    promoter_kyc_verified: bool = False
    project_documents_verified: bool = False

    # Repris tel quel dans la réponse : permet à Laravel d'apparier les scores
    # d'un lot sans se fier à l'ordre.
    project_id: int | None = None

    def to_features(self) -> ProjectFeatures:
        return ProjectFeatures(
            region=self.region or "Autre",
            category=self.category or "résidentiel",
            funding_goal=self.funding_goal,
            expected_return_rate=self.expected_return_rate,
            duration_months=self.duration_months,
            min_investment=self.min_investment,
            promoter_completed_projects=self.promoter_completed_projects,
            promoter_active_projects=self.promoter_active_projects,
            promoter_months_active=self.promoter_months_active,
            promoter_kyc_verified=self.promoter_kyc_verified,
            project_documents_verified=self.project_documents_verified,
        )


class BatchPayload(BaseModel):
    projects: list[ProjectPayload] = Field(default_factory=list, max_length=200)


def _bundle():
    if STATE["bundle"] is None:
        raise HTTPException(status_code=503, detail=STATE["error"] or "Modèle non chargé.")
    return STATE["bundle"]


@app.get("/health")
def health() -> dict:
    bundle = STATE["bundle"]
    return {
        "status": "ok" if bundle is not None else "model_missing",
        "service": "andtabbax-scoring",
        "model_version": bundle.version if bundle else None,
        "metrics": bundle.metrics if bundle else None,
        "error": STATE["error"],
    }


@app.post("/score")
def score(payload: ProjectPayload) -> dict:
    result = model.predict(_bundle(), payload.to_features())
    return {"project_id": payload.project_id, **result}


@app.post("/score/batch")
def score_batch(payload: BatchPayload) -> dict:
    bundle = _bundle()
    scores = [
        {"project_id": p.project_id, **model.predict(bundle, p.to_features())}
        for p in payload.projects
    ]
    return {"count": len(scores), "scores": scores}
