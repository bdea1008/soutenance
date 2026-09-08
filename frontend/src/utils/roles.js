/**
 * Rôles sans espace personnel : ni tableau de bord, ni dossier KYC — une
 * console ou une consultation dédiée en tient lieu. Point de vérité unique,
 * consulté par la connexion, les gardes de route et les pages qui redirigent
 * ces rôles hors de leur espace (`Dashboard.jsx`, `Kyc.jsx`).
 */
const STAFF_HOME = {
  admin: '/admin',
  legal: '/verification-legale',
}

/** Destination « maison » du rôle, ou `null` s'il a un espace personnel normal. */
export function staffHome(role) {
  return STAFF_HOME[role] ?? null
}
