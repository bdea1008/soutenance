# Service de scoring AndTabbax (§8) — FastAPI + scikit-learn.
#
# Le modèle est entraîné pendant la construction de l'image (données simulées,
# graine fixe : le résultat est reproductible) plutôt que copié depuis l'hôte.
# L'image ne dépend donc d'aucun artefact local, et le conteneur démarre sans
# rien avoir à écrire sur disque.
FROM python:3.12-slim

ENV PYTHONDONTWRITEBYTECODE=1 \
    PYTHONUNBUFFERED=1 \
    AI_HOST=0.0.0.0 \
    AI_PORT=8001

WORKDIR /app

COPY requirements.txt ./
RUN pip install --no-cache-dir -r requirements.txt

COPY . .

# Entraînement initial (~1 min) : models/ est exclu par .dockerignore.
RUN python train.py

EXPOSE 8001

CMD ["sh", "-c", "exec uvicorn app:app --host \"$AI_HOST\" --port \"$AI_PORT\""]
