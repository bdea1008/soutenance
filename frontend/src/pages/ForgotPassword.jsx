import { useState } from 'react'
import { Link } from 'react-router-dom'
import { requestPasswordReset } from '../api/auth'
import { errorMessage } from '../api/client'
import BackButton from '../components/BackButton'
import Icon from '../components/Icon'
import logo from '../assets/logo.png'

/**
 * Mot de passe oublié — étape 1 : demander le lien.
 *
 * L'écran ne dit jamais si l'adresse est connue de la plateforme : l'API
 * renvoie exprès le même message dans les deux cas, et l'interface ne doit
 * pas défaire cette précaution en distinguant les deux situations.
 *
 * Le bloc « simulation » n'apparaît que sur un environnement de démonstration
 * (MAIL_SIMULATION_REVEAL côté serveur). C'est lui qui rend le parcours
 * montrable sans boîte mail réelle : il ouvre le message tel qu'il aurait été
 * reçu. Sur un environnement branché à un vrai serveur d'envoi, l'API ne le
 * renvoie pas et la page se termine sur le simple accusé de demande.
 */
export default function ForgotPassword() {
  const [email, setEmail] = useState('')
  const [sent, setSent] = useState(null)
  const [error, setError] = useState('')
  const [loading, setLoading] = useState(false)

  async function handleSubmit(e) {
    e.preventDefault()
    setError('')
    setLoading(true)
    try {
      setSent(await requestPasswordReset(email))
    } catch (err) {
      setError(errorMessage(err, 'Demande impossible pour le moment.'))
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="auth-wrap">
      <BackButton label="Connexion" />

      <div className="card auth-card">
        <img src={logo} alt="AndTabbax" className="brand-full" />

        {sent ? (
          <>
            <h1 style={{ fontSize: '1.5rem' }}>Vérifiez votre boîte mail</h1>
            <div className="alert alert--success">{sent.message}</div>

            {sent.simulation && <SimulationPanel simulation={sent.simulation} />}

            <p className="auth-switch text-muted">
              Vous n’avez rien reçu ?{' '}
              <button type="button" className="link-button" onClick={() => setSent(null)}>
                Réessayer avec une autre adresse
              </button>
            </p>
          </>
        ) : (
          <>
            <h1 style={{ fontSize: '1.5rem' }}>Mot de passe oublié</h1>
            <p className="text-muted">
              Indiquez l’adresse email de votre compte. Nous vous enverrons un lien pour
              choisir un nouveau mot de passe.
            </p>

            {error && <div className="alert alert--error">{error}</div>}

            <form onSubmit={handleSubmit}>
              <div className="field">
                <label htmlFor="email">Email</label>
                <input
                  id="email"
                  name="email"
                  type="email"
                  value={email}
                  onChange={(e) => setEmail(e.target.value)}
                  required
                  autoComplete="email"
                  autoFocus
                />
              </div>
              <button className="btn btn--primary btn--block" disabled={loading}>
                {loading ? 'Envoi…' : 'Envoyer le lien'}
              </button>
            </form>
          </>
        )}

        <p className="auth-switch text-muted">
          Vous vous en souvenez ? <Link to="/connexion">Se connecter</Link>
        </p>
      </div>
    </div>
  )
}

/**
 * Ce que l'utilisateur aurait reçu dans sa boîte mail.
 *
 * Deux issues volontairement distinctes : ouvrir le message (on montre la
 * chaîne d'envoi complète, c'est ce qui prouve qu'elle fonctionne) ou aller
 * droit au formulaire (on continue le parcours sans détour). Le lien de
 * réinitialisation lui-même n'est pas affiché en clair : c'est un secret,
 * même en démonstration — il se lit dans le message, comme en vrai.
 */
function SimulationPanel({ simulation }) {
  return (
    <div className="sim-panel">
      <p className="sim-panel__head">
        <Icon name="mail" size={18} />
        <span>Environnement de démonstration</span>
      </p>

      <p className="sim-panel__note">{simulation.notice}</p>

      <dl className="sim-panel__meta">
        <dt>Destinataire</dt>
        <dd>{simulation.to}</dd>
        <dt>Objet</dt>
        <dd>{simulation.subject}</dd>
      </dl>

      <div className="sim-panel__actions">
        <a
          className="btn btn--primary"
          href={simulation.preview_url}
          target="_blank"
          rel="noreferrer"
        >
          Ouvrir le message reçu
        </a>
        {simulation.reset_url && (
          <a className="btn btn--ghost" href={simulation.reset_url}>
            Aller directement au formulaire
          </a>
        )}
      </div>
    </div>
  )
}
