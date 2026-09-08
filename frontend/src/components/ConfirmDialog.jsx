import { useEffect, useRef, useState } from 'react'
import Icon from './Icon'

/**
 * Fenêtre de confirmation — celle qui annonce un refus et propose la suite.
 *
 * Remplace `window.confirm`, qui affichait « localhost:5173 says » au-dessus du
 * message : la boîte du navigateur ne se met pas en forme, ne sait pas nommer
 * ses boutons autrement que « OK / Cancel », et sort visuellement de
 * l'application au moment précis où il faut inspirer confiance.
 *
 * Même mécanique que `CheckoutDialog` — Échap, clic sur le voile, verrou du
 * défilement, focus déplacé — et même voile vert, pour que les deux fenêtres de
 * l'application se ressemblent. La différence est le propos : celle-ci ne
 * demande rien à remplir, elle explique et oriente.
 *
 * Le bouton d'action porte le focus à l'ouverture : c'est la suite naturelle,
 * et « Entrée » doit y mener sans détour.
 *
 * `prompt` ajoute une saisie et remplace alors `window.prompt` : le motif d'une
 * suspension ou d'un retrait se demande dans la même fenêtre que la
 * confirmation, parce que c'est la même décision. Son exigence est vérifiée
 * **avant** la fermeture — l'ancienne version fermait la boîte puis affichait
 * « un motif est requis » en haut de page, obligeant à tout recommencer.
 */
export default function ConfirmDialog({
  open,
  tone = 'warning',
  icon = 'alert',
  eyebrow = null,
  title,
  subtitle = null,
  message = null,
  items = [],
  progress = null,
  // { label, placeholder, hint, required, requiredMessage, maxLength }
  prompt = null,
  confirmLabel = 'Continuer',
  cancelLabel = 'Annuler',
  // Ton du bouton d'action : « danger » pour ce qui détruit ou retire.
  // Confirmer une suppression et suivre un lien ne s'annoncent pas pareil.
  confirmTone = 'primary',
  // La flèche ne se met que sur une action qui emmène ailleurs. Sur
  // « Supprimer », elle promettrait une navigation qui n'a pas lieu.
  arrow = false,
  onConfirm,
  onCancel,
}) {
  const confirmRef = useRef(null)
  const promptRef = useRef(null)

  const [value, setValue] = useState('')
  const [invalid, setInvalid] = useState('')

  useEffect(() => {
    if (!open) return

    function onKeyDown(e) {
      if (e.key === 'Escape') onCancel()
    }

    document.addEventListener('keydown', onKeyDown)

    const previousOverflow = document.body.style.overflow
    document.body.style.overflow = 'hidden'

    // Avec une saisie, le focus va au champ : c'est ce qu'on vient remplir.
    // Sans elle, au bouton d'action, pour que « Entrée » suffise.
    if (prompt) promptRef.current?.focus()
    else confirmRef.current?.focus()

    return () => {
      document.removeEventListener('keydown', onKeyDown)
      document.body.style.overflow = previousOverflow
    }
  }, [open, onCancel, prompt])

  // Remise à zéro entre deux ouvertures : la fenêtre est réutilisée par le
  // crochet, un motif saisi puis abandonné ne doit pas resurgir plus tard.
  useEffect(() => {
    if (!open) {
      setValue('')
      setInvalid('')
    }
  }, [open])

  if (!open) return null

  function submit(e) {
    e.preventDefault()

    if (!prompt) {
      onConfirm(true)
      return
    }

    const answer = value.trim()

    if (prompt.required && answer === '') {
      setInvalid(prompt.requiredMessage ?? 'Ce champ est obligatoire.')
      promptRef.current?.focus()
      return
    }

    onConfirm(answer)
  }

  // Une liste de seize pièces transformerait la fenêtre en page. On en montre
  // assez pour situer l'effort restant, le détail exhaustif est sur la page du
  // dossier — c'est justement là que le bouton mène.
  const shown = items.slice(0, 5)
  const hidden = items.length - shown.length

  return (
    <div
      className="confirm-overlay"
      onMouseDown={(e) => { if (e.target === e.currentTarget) onCancel() }}
    >
      <div
        className="confirm"
        role="alertdialog"
        aria-modal="true"
        aria-labelledby="confirm-title"
        aria-describedby={message ? 'confirm-message' : undefined}
      >
        <div className="confirm__head">
          <span className={`confirm__icon confirm__icon--${tone}`}>
            <Icon name={icon} size={22} />
          </span>
          <div className="confirm__heading">
            {eyebrow && <p className="eyebrow" style={{ margin: 0 }}>{eyebrow}</p>}
            <h2 id="confirm-title" className="confirm__title">{title}</h2>
            {subtitle && <p className="confirm__subtitle">{subtitle}</p>}
          </div>
        </div>

        {/* En formulaire même sans saisie : valider est un envoi, et « Entrée »
            doit confirmer depuis le champ comme depuis le bouton. */}
        <form onSubmit={submit}>
          <div className="confirm__body">
            {message && <p id="confirm-message" className="confirm__message">{message}</p>}

            {progress && progress.required > 0 && (
              <div className="confirm__progress">
                <div className="confirm__progress-head">
                  <span>Dossier complété</span>
                  <b>{progress.satisfied} / {progress.required}</b>
                </div>
                <div className="dossier-bar" style={{ maxWidth: 'none', marginTop: '0.5rem' }}>
                  <div
                    className="dossier-bar__fill"
                    style={{ width: `${(progress.satisfied / progress.required) * 100}%` }}
                  />
                </div>
              </div>
            )}

            {shown.length > 0 && (
              <ul className="confirm__list">
                {shown.map((item) => (
                  <li key={item.type ?? item.label} className="confirm__item">
                    <span className="confirm__item-label">{item.label}</span>
                    {item.status_label && (
                      <span className={`badge ${STATUS_TONE[item.status] ?? ''}`}>{item.status_label}</span>
                    )}
                  </li>
                ))}
                {hidden > 0 && (
                  <li className="confirm__item confirm__item--more">
                    et {hidden} autre{hidden > 1 ? 's' : ''} pièce{hidden > 1 ? 's' : ''}
                  </li>
                )}
              </ul>
            )}

            {prompt && (
              <div className="confirm__field">
                <label htmlFor="confirm-prompt">{prompt.label}</label>
                <textarea
                  id="confirm-prompt"
                  ref={promptRef}
                  rows={3}
                  value={value}
                  maxLength={prompt.maxLength ?? 500}
                  placeholder={prompt.placeholder}
                  aria-invalid={invalid ? 'true' : undefined}
                  aria-describedby={invalid ? 'confirm-prompt-error' : undefined}
                  onChange={(e) => { setValue(e.target.value); if (invalid) setInvalid('') }}
                />
                {invalid
                  ? <small id="confirm-prompt-error" className="confirm__field-error">{invalid}</small>
                  : prompt.hint && <small className="text-muted">{prompt.hint}</small>}
              </div>
            )}
          </div>

          <div className="confirm__foot">
            <button type="button" className="btn btn--ghost" onClick={onCancel}>
              {cancelLabel}
            </button>
            <button
              type="submit"
              className={`btn ${confirmTone === 'danger' ? 'btn--danger' : 'btn--primary'}`}
              ref={confirmRef}
            >
              {confirmLabel}{arrow ? ' →' : ''}
            </button>
          </div>
        </form>
      </div>
    </div>
  )
}

const STATUS_TONE = {
  approved: 'badge--risk-low',
  pending: 'badge--risk-medium',
  rejected: 'badge--risk-high',
  expired: 'badge--risk-high',
}
