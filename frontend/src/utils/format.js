/** Formate un montant en francs CFA (XOF), sans décimales. */
export function formatFCFA(amount) {
  const n = Number(amount) || 0
  return new Intl.NumberFormat('fr-FR').format(n) + ' FCFA'
}

/**
 * Version compacte pour les grands montants (ex : 150 M FCFA).
 *
 * Séparateur décimal français, comme `formatCompactNumber` juste en dessous :
 * « 2.3 M FCFA » côtoyait « 2,3 M » sur le même écran selon l'endroit d'où
 * venait le chiffre.
 */
export function formatFCFACompact(amount) {
  const n = Number(amount) || 0
  const short = (value, unit) =>
    value.toFixed(1).replace('.0', '').replace('.', ',') + ' ' + unit + ' FCFA'

  if (n >= 1_000_000_000) return short(n / 1_000_000_000, 'Md')
  if (n >= 1_000_000) return short(n / 1_000_000, 'M')
  if (n >= 1_000) return Math.round(n / 1_000) + ' k FCFA'
  return n + ' FCFA'
}

/**
 * Nombre compact sans devise — pour les graduations d'axe, où l'unité est déjà
 * portée par le titre du graphique et où « FCFA » ferait déborder le libellé.
 */
export function formatCompactNumber(amount) {
  const n = Number(amount) || 0
  if (n >= 1_000_000_000) return (n / 1_000_000_000).toFixed(1).replace('.0', '').replace('.', ',') + ' Md'
  if (n >= 1_000_000) return (n / 1_000_000).toFixed(1).replace('.0', '').replace('.', ',') + ' M'
  if (n >= 1_000) return Math.round(n / 1_000) + ' k'
  return String(n)
}

/** Formate un pourcentage (ex : 14.5 → "14,5 %"). */
export function formatPercent(value) {
  if (value === null || value === undefined) return '—'
  return String(value).replace('.', ',') + ' %'
}

/**
 * Date ISO → « 26 juillet 2026 ».
 *
 * La date de publication s'affiche au catalogue, sur la fiche projet et dans
 * l'espace promoteur : elle s'y écrit de la même façon partout. Plusieurs
 * pages gardent encore leur propre copie de ce formatage, héritée d'avant —
 * les prochaines devraient venir puiser ici.
 */
export function formatDate(value) {
  if (!value) return '—'
  return new Date(value).toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' })
}
