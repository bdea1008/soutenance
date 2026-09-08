#!/usr/bin/env bash
# Lance le service de scoring AndTabbax.
#
# Les dépendances Python vivent dans .libs/ (installées avec
# `pip install --target .libs -r requirements.txt`) : cette machine n'a pas
# le paquet python3-venv et pip refuse d'écrire dans le Python système (PEP 668).
set -euo pipefail
cd "$(dirname "$0")"

export PYTHONPATH="${PYTHONPATH:-}:$(pwd)/.libs"

if [ ! -f models/scoring.joblib ]; then
  echo "Modèle absent — entraînement initial…"
  python3 train.py
fi

exec python3 .libs/bin/uvicorn app:app --host "${AI_HOST:-127.0.0.1}" --port "${AI_PORT:-8001}" "$@"
