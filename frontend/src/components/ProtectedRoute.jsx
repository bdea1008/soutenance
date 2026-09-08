import { Navigate, useLocation } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'
import { staffHome } from '../utils/roles'

/**
 * Barrière de niveau 2 : réserve une route aux utilisateurs authentifiés.
 * Redirige vers la connexion en conservant la destination initiale.
 *
 * `roles` (optionnel) restreint en plus la route à certains rôles ; un
 * utilisateur connecté mais non autorisé est renvoyé vers son tableau de bord.
 */
export default function ProtectedRoute({ children, roles }) {
  const { user, loading } = useAuth()
  const location = useLocation()

  if (loading) return <div className="spinner" />
  if (!user) return <Navigate to="/connexion" state={{ from: location }} replace />

  // Renvoi vers l'accueil de son propre espace. Administrateur et rôle
  // juridique n'ont pas de tableau de bord personnel : une console ou une
  // consultation dédiée en tient lieu.
  if (roles && !roles.includes(user.role)) {
    return <Navigate to={staffHome(user.role) ?? '/tableau-de-bord'} replace />
  }

  return children
}
