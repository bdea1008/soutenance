import { useEffect, useRef } from 'react'
import Icon from './Icon'

/**
 * Écran de paiement, commun à l'investissement et à l'abonnement.
 *
 * Les deux parcours posent la même question — *je paie quoi, avec quoi, et
 * combien aujourd'hui ?* — et méritaient la même réponse : un formulaire à
 * gauche, un récapitulatif chiffré à droite, le total dû détaché du reste.
 * Les avoir écrits deux fois aurait produit deux paiements d'aspect différent
 * dans la même application.
 *
 * En fenêtre modale et non en page : on paie *depuis* la fiche projet ou la
 * grille de paliers, sans les perdre de vue ni perdre l'état de la page —
 * un paiement refusé ou abandonné doit ramener exactement d'où l'on venait.
 *
 * `<dialog>` natif n'est pas utilisé : son `::backdrop` ne se style pas de
 * façon homogène et `showModal()` impose son propre cycle d'ouverture, alors
 * que l'état vit déjà dans React. Ce qu'il apporte vraiment — fermeture au
 * clavier, verrouillage du défilement, focus déplacé — tient en dix lignes.
 */
export default function CheckoutDialog({
  open,
  title,
  eyebrow,
  subtitle,
  summary,
  children,
  onClose,
  onSubmit,
  submitLabel = 'Confirmer',
  submitting = false,
  error = '',
  note = null,
}) {
  const panelRef = useRef(null)

  useEffect(() => {
    if (!open) return

    function onKeyDown(e) {
      // Jamais pendant l'envoi : le paiement est peut-être déjà parti, fermer
      // laisserait croire qu'il a été annulé.
      if (e.key === 'Escape' && !submitting) onClose()
    }

    document.addEventListener('keydown', onKeyDown)

    // Verrouille le défilement de la page derrière la fenêtre — sinon la
    // molette fait défiler la fiche projet sous le formulaire de paiement.
    const previousOverflow = document.body.style.overflow
    document.body.style.overflow = 'hidden'

    // Le focus part sur le panneau : la lecture commence en haut du paiement,
    // pas au milieu de la page qu'on vient de recouvrir.
    panelRef.current?.focus()

    return () => {
      document.removeEventListener('keydown', onKeyDown)
      document.body.style.overflow = previousOverflow
    }
  }, [open, submitting, onClose])

  if (!open) return null

  return (
    <div
      className="checkout-overlay"
      // Le clic hors du panneau ferme, comme partout ailleurs — mais seulement
      // sur le voile lui-même, pas sur un clic parti d'un champ et relâché
      // en dehors.
      onMouseDown={(e) => { if (e.target === e.currentTarget && !submitting) onClose() }}
    >
      <div
        className="checkout"
        role="dialog"
        aria-modal="true"
        aria-labelledby="checkout-title"
        tabIndex={-1}
        ref={panelRef}
      >
        <header className="checkout__head">
          <div>
            {eyebrow && <p className="eyebrow" style={{ margin: 0 }}>{eyebrow}</p>}
            <h2 id="checkout-title" className="checkout__title">{title}</h2>
            {subtitle && <p className="text-muted" style={{ margin: '0.25rem 0 0' }}>{subtitle}</p>}
          </div>
          <button
            type="button"
            className="checkout__close"
            onClick={onClose}
            disabled={submitting}
            aria-label="Fermer"
          >
            <Icon name="close" size={20} />
          </button>
        </header>

        <form className="checkout__grid" onSubmit={onSubmit}>
          <div className="checkout__main">
            {children}

            {error && <div className="alert alert--error" style={{ marginTop: '1.25rem' }}>{error}</div>}

            <button className="btn btn--primary btn--block checkout__submit" disabled={submitting}>
              {submitting ? 'Traitement…' : submitLabel}
            </button>

            {note && <p className="checkout__note">{note}</p>}
          </div>

          {/* Récapitulatif. Passe au-dessus du formulaire sous 900 px : on ne
              choisit pas un moyen de paiement avant de savoir ce qu'on paie. */}
          <aside className="checkout__summary">
            <p className="checkout__summary-head">{summary.heading ?? 'Récapitulatif'}</p>

            <dl className="checkout__lines">
              {summary.lines.map((line) => (
                <div key={line.label} className="checkout__line">
                  <dt>
                    {line.label}
                    {line.hint && <span className="checkout__line-hint">{line.hint}</span>}
                  </dt>
                  <dd className={line.strong ? 'checkout__line-strong' : undefined}>{line.value}</dd>
                </div>
              ))}
            </dl>

            <div className="checkout__total">
              <span>{summary.total.label}</span>
              <b>{summary.total.value}</b>
            </div>

            {summary.note && <p className="checkout__summary-note">{summary.note}</p>}
          </aside>
        </form>
      </div>
    </div>
  )
}
