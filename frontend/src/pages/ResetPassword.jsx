import { useEffect, useState } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router-dom'
import { checkResetToken, resetPassword } from '../api/auth'
import { errorMessage } from '../api/client'
import BackButton from '../components/BackButton'
import logo from '../assets/logo.png'

/**
 * Mot de passe oublié — étape 3 : choisir le nouveau mot de passe.
 *
 * L'adresse et le jeton arrivent par l'URL du lien reçu par email : ce sont
 * les deux seules preuves d'identité du parcours, il n'y a pas de session à
 * ce stade. L'adresse est affichée mais jamais modifiable — la changer
 * reviendrait à réinitialiser le mot de passe d'un autre compte avec un jeton
 * qui ne le concerne pas (le serveur refuserait, mais autant ne pas suggérer
 * le geste).
 *
 * La validité du lien est vérifiée à l'ouverture, avant que l'utilisateur ne
 * compose et confirme un mot de passe pour rien.
 */
export default function ResetPassword() {
  const [params] = useSearchParams()
  const navigate = useNavigate()

  const email = params.get('email') ?? ''
  const token = params.get('token') ?? ''

  // null = vérification en cours, true/false = verdict du serveur.
  const [linkValid, setLinkValid] = useState(null)
  const [form, setForm] = useState({ password: '', passwordConfirmation: '' })
  const [error, setError] = useState('')
  const [done, setDone] = useState(false)
  const [loading, setLoading] = useState(false)

  useEffect(() => {
    if (!email || !token) {
      setLinkValid(false)
      return
    }

    let cancelled = false

    checkResetToken({ email, token })
      .then((res) => { if (!cancelled) setLinkValid(res.valid) })
      // Une panne réseau n'est pas un lien invalide : on laisse le formulaire
      // s'ouvrir, le serveur tranchera à la validation.
      .catch(() => { if (!cancelled) setLinkValid(true) })

    return () => { cancelled = true }
  }, [email, token])

  function update(e) {
    setForm({ ...form, [e.target.name]: e.target.value })
  }

  async function handleSubmit(e) {
    e.preventDefault()
    setError('')
    setLoading(true)
    try {
      await resetPassword({ email, token, ...form })
      setDone(true)
      // Le compte est de nouveau accessible : on renvoie vers la connexion
      // plutôt que de laisser l'utilisateur chercher la sortie lui-même.
      setTimeout(() => navigate('/connexion', { replace: true }), 2500)
    } catch (err) {
      // 422 `invalid_token` : le lien a expiré ou servi entre-temps. Le
      // formulaire n'a plus lieu d'être, on bascule sur l'écran d'impasse.
      if (err.response?.data?.code === 'invalid_token') {
        setLinkValid(false)
        return
      }
      setError(errorMessage(err, 'Modification impossible.'))
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="auth-wrap">
      <BackButton label="Connexion" />

      <div className="card auth-card">
        <img src={logo} alt="AndTabbax" className="brand-full" />

        {linkValid === null && (
          <>
            <h1 style={{ fontSize: '1.5rem' }}>Vérification du lien…</h1>
            <div className="spinner" />
          </>
        )}

        {linkValid === false && (
          <>
            <h1 style={{ fontSize: '1.5rem' }}>Lien expiré</h1>
            <div className="alert alert--error">
              Ce lien de réinitialisation n’est plus valable : il a expiré ou a déjà servi.
              Chaque lien ne fonctionne qu’une fois.
            </div>
            <Link to="/mot-de-passe-oublie" className="btn btn--primary btn--block">
              Demander un nouveau lien
            </Link>
          </>
        )}

        {linkValid === true && done && (
          <>
            <h1 style={{ fontSize: '1.5rem' }}>Mot de passe modifié</h1>
            <div className="alert alert--success">
              Votre mot de passe a été changé. Redirection vers la connexion…
            </div>
            <Link to="/connexion" className="btn btn--primary btn--block">
              Se connecter maintenant
            </Link>
          </>
        )}

        {linkValid === true && !done && (
          <>
            <h1 style={{ fontSize: '1.5rem' }}>Nouveau mot de passe</h1>
            <p className="text-muted">
              Compte : <strong>{email}</strong>
            </p>

            {error && <div className="alert alert--error">{error}</div>}

            <form onSubmit={handleSubmit}>
              <div className="field">
                <label htmlFor="password">Nouveau mot de passe</label>
                <input
                  id="password"
                  name="password"
                  type="password"
                  value={form.password}
                  onChange={update}
                  required
                  minLength={8}
                  autoComplete="new-password"
                  autoFocus
                />
                <small className="text-muted">8 caractères minimum.</small>
              </div>
              <div className="field">
                <label htmlFor="passwordConfirmation">Confirmer le mot de passe</label>
                <input
                  id="passwordConfirmation"
                  name="passwordConfirmation"
                  type="password"
                  value={form.passwordConfirmation}
                  onChange={update}
                  required
                  minLength={8}
                  autoComplete="new-password"
                />
              </div>
              <button className="btn btn--primary btn--block" disabled={loading}>
                {loading ? 'Modification…' : 'Changer mon mot de passe'}
              </button>
            </form>
          </>
        )}

        <p className="auth-switch text-muted">
          <Link to="/connexion">Retour à la connexion</Link>
        </p>
      </div>
    </div>
  )
}
