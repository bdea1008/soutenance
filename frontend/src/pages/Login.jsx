import { useState } from 'react'
import { Link, useLocation, useNavigate, useSearchParams } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'
import { errorMessage } from '../api/client'
import { staffHome } from '../utils/roles'
import BackButton from '../components/BackButton'
import logo from '../assets/logo.png'

/**
 * Où atterrit-on une fois connecté ?
 *
 * Administrateur et rôle juridique vont sur leur propre console : ni l'un ni
 * l'autre n'a de tableau de bord personnel. On respecte la page qu'ils
 * demandaient avant d'être renvoyés vers la connexion, à condition qu'elle
 * relève de leur espace — sinon ils rebondiraient aussitôt (session expirée
 * sur /tableau-de-bord, par exemple).
 */
function landingPath(account, from) {
  const home = staffHome(account?.role)

  if (home) {
    return from?.startsWith(home) ? from : home
  }

  return from ?? '/tableau-de-bord'
}

export default function Login() {
  const { login } = useAuth()
  const navigate = useNavigate()
  const location = useLocation()
  const [params] = useSearchParams()
  // Page demandée avant d'être renvoyé ici par ProtectedRoute, s'il y en a une.
  const from = location.state?.from?.pathname ?? null

  // Session fermée en cours de route par une désactivation administrative :
  // sans explication, l'utilisateur croirait à un bug.
  const suspended = params.get('suspendu') === '1'

  const [form, setForm] = useState({ email: '', password: '' })
  const [error, setError] = useState('')
  const [loading, setLoading] = useState(false)

  function update(e) {
    setForm({ ...form, [e.target.name]: e.target.value })
  }

  async function handleSubmit(e) {
    e.preventDefault()
    setError('')
    setLoading(true)
    try {
      const account = await login(form)
      navigate(landingPath(account, from), { replace: true })
    } catch (err) {
      setError(errorMessage(err, 'Connexion impossible.'))
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="auth-wrap">
      <BackButton />

      <div className="card auth-card">
        <img src={logo} alt="AndTabbax" className="brand-full" />
        <h1 style={{ fontSize: '1.6rem' }}>Connexion</h1>
        <p className="text-muted">Accédez au détail des projets et à votre espace.</p>

        {suspended && !error && (
          <div className="alert alert--error">
            Votre compte a été désactivé par l’administration et votre session a été fermée.
            Contactez-nous pour en connaître le motif.
          </div>
        )}

        {error && <div className="alert alert--error">{error}</div>}

        <form onSubmit={handleSubmit}>
          <div className="field">
            <label htmlFor="email">Email</label>
            <input id="email" name="email" type="email" value={form.email} onChange={update} required autoComplete="email" />
          </div>
          <div className="field">
            <label htmlFor="password">Mot de passe</label>
            <input id="password" name="password" type="password" value={form.password} onChange={update} required autoComplete="current-password" />
          </div>
          <p className="auth-aside">
            <Link to="/mot-de-passe-oublie">Mot de passe oublié ?</Link>
          </p>
          <button className="btn btn--primary btn--block" disabled={loading}>
            {loading ? 'Connexion…' : 'Se connecter'}
          </button>
        </form>

        <p className="auth-switch text-muted">
          Pas encore de compte ? <Link to="/inscription">Créer un compte</Link>
        </p>
      </div>
    </div>
  )
}
