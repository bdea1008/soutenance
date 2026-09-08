/**
 * Palette de visualisation AndTabbax.
 *
 * Les couleurs de séries ne sont pas choisies à l'œil : cet ordre a été validé
 * (script `validate_palette.js` du skill dataviz) contre la surface réelle des
 * cartes (#ffffff), en paires adjacentes — la méthode adaptée à des barres et
 * portions empilées dans un ordre fixe, pas à un nuage de points.
 *   pire paire adjacente : ΔE 8,6 (protanopie) · 16,9 (vision normale)
 *
 * Réajustée le 25/07/2026 sur la nouvelle identité (vert profond + or doux +
 * ivoire, cf. couleurs.jpg) : le vert profond de marque (#183630) est bien
 * trop sombre et désaturé pour porter des données (sous le plancher de
 * chroma) — la série 1 reste donc une version saturée du même vert, comme
 * avant. Le bleu d'information (#3b82f6, cf. remarques) prend la place du
 * bleu générique précédent ; le magenta cède la sienne à une terre chaude,
 * plus proche de l'or de marque, pour rester dans le même registre que
 * l'identité plutôt que d'y importer une teinte froide.
 *
 * L'or et la terre passent sous 3:1 de contraste avec la surface : la règle
 * de compensation impose des libellés visibles et une vue tableau, que tous
 * les graphiques d'ici fournissent.
 *
 * Toute modification de ces valeurs doit être re-validée, pas estimée.
 */

/** Couleurs catégorielles, dans un ordre fixe — jamais recyclé, jamais généré. */
export const SERIES = [
  '#00876a', // 1 — vert (marque, saturé pour tenir le plancher de chroma)
  '#a5731f', // 2 — or (marque, assombri pour la même raison)
  '#3b82f6', // 3 — bleu d'information (remarques)
  '#c15b6b', // 4 — terre chaude (registre de la marque, jamais une teinte froide)
]

/** Teinte unique pour les magnitudes : une catégorie nominale = une couleur. */
export const PRIMARY = SERIES[0]

/** Piste des jauges : pas un gris, un pas clair de la même rampe. */
export const TRACK = '#d4ece5'

/**
 * Encre et chrome — le texte ne porte jamais la couleur des données.
 * Mêmes teintes que le reste de l'interface (`--color-text`,
 * `--color-text-muted`) : un graphique n'est pas un sous-système à part,
 * juste un texte qui aurait pris la forme d'une barre.
 */
export const INK = {
  primary: '#17241f',
  secondary: '#655e50',
  muted: '#948c78',
  grid: '#ece3d1',
  axis: '#d6c9ab',
  surface: '#ffffff',
}

/**
 * Couleurs d'état, réservées : elles ne servent jamais de « série 5 » et
 * s'accompagnent toujours d'un libellé, jamais de la couleur seule.
 */
export const STATUS = {
  good: '#0ca30c',
  warning: '#fab219',
  serious: '#ec835a',
  critical: '#d03b3b',
  neutral: '#8a9691',
}

/** État KYC → couleur d'état. */
export const KYC_STATUS = {
  verified: STATUS.good,
  pending: STATUS.warning,
  rejected: STATUS.critical,
  none: STATUS.neutral,
}

/** Niveau de risque IA → couleur d'état. */
export const RISK_STATUS = {
  low: STATUS.good,
  medium: STATUS.warning,
  high: STATUS.critical,
}
