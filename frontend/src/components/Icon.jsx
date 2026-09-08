/**
 * Jeu d'icônes de l'application.
 *
 * Les emojis remplissaient ce rôle jusqu'ici : ils changent de dessin d'un
 * système à l'autre, ne prennent pas la couleur du texte, et lus par une
 * synthèse vocale ils annoncent « bâtiment en construction » au milieu d'une
 * phrase. Ce sont des tracés au trait, à `currentColor`, dessinés dans une
 * grille de 24 — donc cohérents entre eux et avec le texte qui les entoure.
 *
 * Décoratives par défaut (`aria-hidden`) : le libellé est toujours écrit à
 * côté. Passer `title` quand l'icône est seule porteuse de sens.
 */

const PATHS = {
  bell: (
    <>
      <path d="M18 8a6 6 0 1 0-12 0c0 7-3 9-3 9h18s-3-2-3-9" />
      <path d="M13.7 21a2 2 0 0 1-3.4 0" />
    </>
  ),
  pin: (
    <>
      <path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 1 1 16 0z" />
      <circle cx="12" cy="10" r="3" />
    </>
  ),
  spark: (
    <>
      <path d="M11 3l1.9 5.1L18 10l-5.1 1.9L11 17l-1.9-5.1L4 10l5.1-1.9z" />
      <path d="M18.5 14.5l.8 2.2 2.2.8-2.2.8-.8 2.2-.8-2.2-2.2-.8 2.2-.8z" />
    </>
  ),
  building: (
    <>
      <path d="M3 21h18" />
      <path d="M5 21V7l7-4 7 4v14" />
      <path d="M10 21v-5h4v5" />
      <path d="M9 9h1.5M13.5 9H15M9 12.5h1.5M13.5 12.5H15" />
    </>
  ),
  wallet: (
    <>
      <rect x="2.5" y="6" width="19" height="12" rx="2.5" />
      <circle cx="12" cy="12" r="2.5" />
      <path d="M6 12h.01M18 12h.01" />
    </>
  ),
  check: (
    <>
      <circle cx="12" cy="12" r="9" />
      <path d="M8.2 12.4l2.6 2.6 5-5.4" />
    </>
  ),
  alert: (
    <>
      <path d="M10.3 4.9a2 2 0 0 1 3.4 0l7.2 12.6a2 2 0 0 1-1.7 3H4.8a2 2 0 0 1-1.7-3z" />
      <path d="M12 10v4M12 17.5h.01" />
    </>
  ),
  target: (
    <>
      <circle cx="12" cy="12" r="9" />
      <circle cx="12" cy="12" r="5" />
      <circle cx="12" cy="12" r="1.4" />
    </>
  ),
  ban: (
    <>
      <circle cx="12" cy="12" r="9" />
      <path d="M5.6 5.6l12.8 12.8" />
    </>
  ),
  undo: (
    <>
      <path d="M3.5 8H14a5 5 0 0 1 0 10H8.5" />
      <path d="M7 4.5L3.5 8 7 11.5" />
    </>
  ),
  star: (
    <path d="M12 3.5l2.6 5.3 5.9.9-4.3 4.1 1 5.8-5.2-2.7-5.2 2.7 1-5.8L3.5 9.7l5.9-.9z" />
  ),
  image: (
    <>
      <rect x="3" y="4" width="18" height="16" rx="2.5" />
      <circle cx="8.5" cy="9.5" r="1.5" />
      <path d="M20.5 15.5L16 11 6 20" />
    </>
  ),
  search: (
    <>
      <circle cx="11" cy="11" r="7" />
      <path d="M20 20l-4-4" />
    </>
  ),
  phone: (
    <>
      <rect x="6.5" y="2.5" width="11" height="19" rx="2.5" />
      <path d="M11 18.5h2" />
    </>
  ),
  users: (
    <>
      <circle cx="9" cy="8" r="4" />
      <path d="M2 20c0-3.9 3.1-6.5 7-6.5s7 2.6 7 6.5" />
      <path d="M17 4.7a4 4 0 0 1 0 6.6" />
      <path d="M18.5 14.3c2.2.8 3.5 2.6 3.5 5.7" />
    </>
  ),
  // Sécurité du compte : mot de passe modifié, lien de réinitialisation.
  lock: (
    <>
      <rect x="4" y="10.5" width="16" height="10.5" rx="2.5" />
      <path d="M8 10.5V7a4 4 0 0 1 8 0v3.5" />
      <path d="M12 14.5v2.5" />
    </>
  ),
  // Carte bancaire : le moyen de paiement qui n'a pas de logo de marque tant
  // que le réseau n'est pas connu.
  card: (
    <>
      <rect x="2.5" y="5" width="19" height="14" rx="2.5" />
      <path d="M2.5 9.5h19" />
      <path d="M6 15h3" />
    </>
  ),
  // Fermeture d'une fenêtre modale.
  close: (
    <path d="M6 6l12 12M18 6L6 18" />
  ),
  // Bouclier : paiement protégé, mention de sécurité.
  shield: (
    <>
      <path d="M12 3l7.5 3v5.5c0 4.4-3 8.1-7.5 9.5-4.5-1.4-7.5-5.1-7.5-9.5V6z" />
      <path d="M9 12.2l2.1 2.1 4-4.3" />
    </>
  ),
  // Enveloppe : boîte d'envoi simulée, messages sortants.
  mail: (
    <>
      <rect x="2.5" y="5" width="19" height="14" rx="2.5" />
      <path d="M3.5 7l8.5 6 8.5-6" />
    </>
  ),
}

export default function Icon({ name, size = 20, className = '', title }) {
  const glyph = PATHS[name]

  if (!glyph) {
    return null
  }

  return (
    <svg
      className={`icon ${className}`.trim()}
      width={size}
      height={size}
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="1.75"
      strokeLinecap="round"
      strokeLinejoin="round"
      role={title ? 'img' : undefined}
      aria-hidden={title ? undefined : true}
      focusable="false"
    >
      {title && <title>{title}</title>}
      {glyph}
    </svg>
  )
}
