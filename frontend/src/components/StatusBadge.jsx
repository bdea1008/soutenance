/**
 * Pastille de statut d'un projet (cycle de vie côté promoteur).
 * Les libellés viennent de l'API (`status_label`) ; la couleur est décidée ici.
 *
 * Deux tons portent un sens particulier plutôt que le triptyque générique
 * bon/attention/mauvais : « financé » est le seul badge à porter l'or de la
 * marque (une collecte bouclée est un jalon, pas un simple état positif) ;
 * « en attente d'examen » et « en chantier » portent le bleu d'information —
 * ce sont des statuts « en cours », exactement le rôle que lui réserve la
 * charte graphique (remarques).
 */
const TONE = {
  draft: '',                        // neutre
  pending_review: 'badge--info',
  published: 'badge--risk-low',
  funded: 'badge--gold',
  in_progress: 'badge--info',
  completed: 'badge--risk-low',
  cancelled: 'badge--risk-high',
}

export default function StatusBadge({ status, label }) {
  return <span className={`badge ${TONE[status] ?? ''}`}>{label || status}</span>
}
