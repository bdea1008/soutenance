import { Link, useNavigate } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'
import NotificationBell from './NotificationBell'
import { staffHome } from '../utils/roles'
import logoMark from '../assets/logo-mark.png'

/**
 * Navigation de l'application, dans l'état qui correspond au visiteur : barre
 * publique, ou barre d'un utilisateur connecté dont le contenu dépend du rôle.
 *
 * Trois zones — marque à gauche, navigation au centre, compte à droite — dans
 * une barre flottante détachée des bords. Les entrées sont du texte nu, pas
 * des boutons encadrés : une dizaine de cadres alignés faisaient un ruban de
 * boîtes là où il ne faut qu'une ligne de mots.
 *
 * Les écrans d'identification ne l'affichent pas du tout — `App.jsx` ne la
 * monte pas sur ces routes.
 */
export default function Navbar() {
  const { user, logout } = useAuth()
  const navigate = useNavigate()

  const isAdmin = user?.role === 'admin'
  const isLegal = user?.role === 'legal'
  const home = staffHome(user?.role)

  async function handleLogout() {
    await logout()
    navigate('/')
  }

  /** Entrées publiques, ou leur équivalent « plateforme entière » par rôle. */
  function browseLinks() {
    // L'administrateur supervise tous les projets et tous les abonnements, le
    // rôle juridique consulte tous les projets en lecture seule. Ni l'un ni
    // l'autre ne parcourt le catalogue en investisseur.
    if (isAdmin) {
      return (
        <>
          <Link to="/admin/projets" className="navbar__link">Projets</Link>
          <Link to="/admin/abonnements" className="navbar__link">Abonnements</Link>
        </>
      )
    }

    if (isLegal) {
      return <Link to="/verification-legale" className="navbar__link">Projets</Link>
    }

    return (
      <>
        <Link to="/projets" className="navbar__link">Projets</Link>
        <Link to="/tarifs" className="navbar__link">Tarifs</Link>
      </>
    )
  }

  /** Espace propre à l'utilisateur connecté — console, ou tableau de bord. */
  function personalLinks() {
    // Point d'entrée unique de chaque console. Ni l'administrateur ni le rôle
    // juridique n'ont d'espace personnel : leur console en tient lieu.
    if (isAdmin) {
      return <Link to="/admin" className="navbar__link">Administration</Link>
    }

    if (isLegal) {
      return <Link to="/verification-legale" className="navbar__link">Consultation</Link>
    }

    return (
      <>
        {/* Raccourci visible tant que la vérification n'est pas acquise. */}
        {!user.kyc_verified && (
          <Link to="/verification" className="navbar__link">Vérification</Link>
        )}
        <Link to="/tableau-de-bord" className="navbar__link">Tableau de bord</Link>
      </>
    )
  }

  return (
    <header className="navbar">
      <div className="container">
        <div className="navbar__inner">
          {/* L'accueil public n'a pas de sens pour un rôle interne : sa page de
              départ est sa propre console. */}
          <Link to={home ?? '/'} className="navbar__brand">
            <img src={logoMark} alt="" className="navbar__logo" width="34" height="34" />
            AndTabbax
          </Link>

          <nav className="navbar__browse">
            {browseLinks()}

            {/* L'espace promoteur n'appartient qu'aux promoteurs : un
                administrateur n'a ni projets à lui, ni abonnement à payer. */}
            {user?.role === 'promoter' && (
              <>
                <Link to="/promoteur/projets" className="navbar__link">Mes projets</Link>
                <Link to="/promoteur/abonnement" className="navbar__link">Abonnement</Link>
              </>
            )}
          </nav>

          <div className="navbar__account">
            {user ? (
              <>
                <span className="navbar__user">Bonjour, {user.first_name}</span>
                {personalLinks()}
                <NotificationBell />
                <button className="navbar__link" onClick={handleLogout}>Déconnexion</button>
              </>
            ) : (
              <>
                <Link to="/connexion" className="navbar__link">Connexion</Link>
                <Link to="/inscription" className="btn btn--primary navbar__cta">Créer un compte</Link>
              </>
            )}
          </div>
        </div>
      </div>
    </header>
  )
}
