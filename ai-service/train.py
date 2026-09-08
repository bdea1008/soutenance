#!/usr/bin/env python3
"""
Entraîne les modèles de scoring et les enregistre dans models/scoring.joblib.

    python train.py [--samples 12000] [--seed 42]

À relancer après toute modification des variables (scoring/features.py) ou des
hypothèses de marché, et le jour où la plateforme dispose d'un historique réel.
"""

from __future__ import annotations

import argparse
import json

from scoring import model


def main() -> None:
    parser = argparse.ArgumentParser(description="Entraînement du scoring AndTabbax")
    parser.add_argument("--samples", type=int, default=12_000, help="taille du jeu simulé")
    parser.add_argument("--seed", type=int, default=42, help="graine aléatoire")
    args = parser.parse_args()

    print(f"Entraînement sur {args.samples} opérations simulées…")
    bundle = model.train(n_samples=args.samples, seed=args.seed)
    path = model.save(bundle)

    print(f"\nModèle enregistré : {path}")
    print(f"Version           : {bundle.version}\n")
    print("Tenue sur le jeu de test :")
    print(json.dumps(bundle.metrics, indent=2, ensure_ascii=False))


if __name__ == "__main__":
    main()
