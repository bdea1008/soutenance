import { Link } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'
import logoMark from '../assets/logo-mark.png'

export default function Footer() {
  const { user } = useAuth()
  const isAdmin = user?.role === 'admin'

  return (
    <footer className="footer">
      <div className="container footer__grid">
        <div style={{ maxWidth: 320 }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: '0.75rem' }}>
            <span className="logo-chip">
              <img src={logoMark} alt="AndTabbax" />
            </span>
            {/* Le nom suit le logo : or doux, comme dans la navbar. */}
            <strong style={{ color: 'var(--color-gold)', fontSize: '1.1rem' }}>AndTabbax</strong>
          </div>
          <p style={{ marginTop: '0.75rem' }}>
            Infrastructure numérique de confiance pour le co-investissement
            immobilier au Sénégal et en Afrique.
          </p>
        </div>
        <div>
          <p style={{ color: 'var(--color-gold)', fontWeight: 600 }}>Plateforme</p>
          {/* Mêmes notions, mais vues de la place de chacun : l'administrateur
              n'a pas de compte à créer ni de tarif à consulter, il supervise. */}
          <p style={{ margin: 0, display: 'grid', gap: '0.35rem' }}>
            {isAdmin ? (
              <>
                <Link to="/admin/projets">Tous les projets</Link>
                <Link to="/admin/abonnements">Tous les abonnements</Link>
                <Link to="/admin/utilisateurs">Comptes</Link>
              </>
            ) : (
              <>
                <Link to="/projets">Projets</Link>
                <Link to="/tarifs">Tarifs promoteur</Link>
                <Link to="/inscription">Créer un compte</Link>
              </>
            )}
          </p>
        </div>
        <div>
          <p style={{ color: 'var(--color-gold)', fontWeight: 600 }}>Contact</p>
          <p>Dakar, Sénégal</p>
        </div>
      </div>
      <div className="container" style={{ marginTop: '1.5rem', fontSize: '0.8rem', opacity: 0.7 }}>
        © {new Date().getFullYear()} AndTabbax — Projet de soutenance.
      </div>
    </footer>
  )
}
