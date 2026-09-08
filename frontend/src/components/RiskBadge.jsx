const LABELS = { low: 'Risque faible', medium: 'Risque modéré', high: 'Risque élevé' }

/** Badge de niveau de risque issu du scoring IA. */
export default function RiskBadge({ level }) {
  if (!level) return null
  return <span className={`badge badge--risk-${level}`}>{LABELS[level] || level}</span>
}
