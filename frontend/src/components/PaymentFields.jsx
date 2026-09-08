import Icon from './Icon'
import PaymentMethodPicker from './PaymentMethod'
import { cardBrand, isMobileMoney, providerLabel, shortBrand } from '../utils/payment'

/**
 * Coordonnées du moyen de paiement (§7.4).
 *
 * Le choix du moyen et la saisie qu'il entraîne sont indissociables : cocher
 * « Carte bancaire » sans que le formulaire de carte apparaisse juste en
 * dessous n'aurait aucun sens. Ce composant porte donc les deux, et les deux
 * parcours qui encaissent — investir, s'abonner — l'affichent tel quel.
 *
 * Ce qui est saisi ici ne va pas tout en base : le serveur ne conserve que le
 * numéro débité (Mobile Money) ou le réseau et les quatre derniers chiffres
 * (carte). Le numéro complet et le cryptogramme ne franchissent la requête que
 * le temps de la valider — voir `App\Support\PaymentInstrument`.
 */

/**
 * Découpe le numéro en groupes lisibles pendant la frappe. American Express
 * se lit 4-6-5, tous les autres réseaux 4 par 4 : imposer des groupes de
 * quatre à une Amex ferait buter la relecture sur la carte physique.
 */
function formatCardNumber(value) {
  const d = value.replace(/\D/g, '').slice(0, 19)
  const groups = cardBrand(d) === 'American Express' ? [4, 6, 5] : [4, 4, 4, 4, 3]

  const parts = []
  let i = 0
  for (const size of groups) {
    if (i >= d.length) break
    parts.push(d.slice(i, i + size))
    i += size
  }

  return parts.join(' ')
}

/** `0928` → `09/28`, la barre s'insérant d'elle-même après le mois. */
function formatExpiry(value) {
  const d = value.replace(/\D/g, '').slice(0, 4)

  return d.length <= 2 ? d : `${d.slice(0, 2)}/${d.slice(2)}`
}

export default function PaymentFields({ providers, value, onChange, disabled = false }) {
  const set = (patch) => onChange({ ...value, ...patch })
  const brand = cardBrand(value.cardNumber)

  return (
    <>
      <PaymentMethodPicker
        providers={providers}
        value={value.provider}
        onChange={(provider) => set({ provider })}
        disabled={disabled}
      />

      {isMobileMoney(value.provider) && (
        <div className="pay-detail">
          <p className="pay-detail__title">Numéro à débiter</p>

          {/* Le numéro du compte est proposé d'abord, déjà sélectionné : c'est
              le cas courant, et il a été vérifié à l'inscription. Payer depuis
              un autre téléphone reste possible — un compte Wave familial, un
              numéro professionnel — mais c'est le choix qu'on fait exprès. */}
          {value.accountPhone && (
            <label className={`pay-choice${!value.useOtherPhone ? ' pay-choice--on' : ''}`}>
              <input
                type="radio"
                name="phone-source"
                checked={!value.useOtherPhone}
                onChange={() => set({ useOtherPhone: false })}
                disabled={disabled}
              />
              <span>
                <b>{value.accountPhone}</b>
                <span className="pay-choice__hint">numéro de votre compte</span>
              </span>
            </label>
          )}

          <label className={`pay-choice${value.useOtherPhone || !value.accountPhone ? ' pay-choice--on' : ''}`}>
            <input
              type="radio"
              name="phone-source"
              checked={value.useOtherPhone || !value.accountPhone}
              onChange={() => set({ useOtherPhone: true })}
              disabled={disabled}
            />
            <span>
              <b>Un autre numéro</b>
              <span className="pay-choice__hint">payer depuis un autre compte {providerLabel(providers, value.provider)}</span>
            </span>
          </label>

          {(value.useOtherPhone || !value.accountPhone) && (
            <div className="field" style={{ margin: '0.7rem 0 0' }}>
              <label htmlFor="pay-phone">Numéro de téléphone</label>
              <input
                id="pay-phone"
                type="tel"
                inputMode="tel"
                autoComplete="tel"
                placeholder="+221 77 123 45 67"
                value={value.otherPhone}
                onChange={(e) => set({ otherPhone: e.target.value })}
                required
                disabled={disabled}
              />
              <small className="text-muted">
                Un numéro sénégalais peut être saisi tel quel (77 123 45 67).
              </small>
            </div>
          )}
        </div>
      )}

      {value.provider === 'card' && (
        <div className="pay-detail">
          <p className="pay-detail__title">Coordonnées de la carte</p>

          <div className="field">
            <label htmlFor="card-number">Numéro de carte</label>
            <div className="field-with-tag">
              <input
                id="card-number"
                inputMode="numeric"
                autoComplete="cc-number"
                placeholder="1234 1234 1234 1234"
                value={value.cardNumber}
                onChange={(e) => set({ cardNumber: formatCardNumber(e.target.value) })}
                required
                disabled={disabled}
              />
              {/* Le réseau s'affiche dès qu'il est reconnaissable : c'est le
                  signe que la saisie part bien, avant même de valider. */}
              {brand && <span className="field-tag">{shortBrand(brand)}</span>}
            </div>
            {/* Sous 520 px, le champ ne fait plus que ~240 px : l'étiquette y
                mangerait la place du numéro. Elle passe alors en dessous, où
                elle dispose de toute la largeur. */}
            {brand && <small className="text-muted only-narrow">Carte {brand} reconnue</small>}
          </div>

          <div className="form-row form-row--2">
            <div className="field">
              <label htmlFor="card-expiry">Date d’expiration</label>
              <input
                id="card-expiry"
                inputMode="numeric"
                autoComplete="cc-exp"
                placeholder="MM/AA"
                value={value.cardExpiry}
                onChange={(e) => set({ cardExpiry: formatExpiry(e.target.value) })}
                required
                disabled={disabled}
              />
            </div>
            <div className="field">
              <label htmlFor="card-cvc">CVC</label>
              <input
                id="card-cvc"
                inputMode="numeric"
                autoComplete="cc-csc"
                placeholder={brand === 'American Express' ? '1234' : '123'}
                maxLength={4}
                value={value.cardCvc}
                onChange={(e) => set({ cardCvc: e.target.value.replace(/\D/g, '').slice(0, 4) })}
                required
                disabled={disabled}
              />
              <small className="text-muted">
                {brand === 'American Express' ? '4 chiffres au recto' : '3 chiffres au dos'}
              </small>
            </div>
          </div>

          <div className="field" style={{ marginBottom: 0 }}>
            <label htmlFor="card-holder">Nom inscrit sur la carte</label>
            <input
              id="card-holder"
              autoComplete="cc-name"
              value={value.cardHolder}
              onChange={(e) => set({ cardHolder: e.target.value })}
              required
              disabled={disabled}
            />
          </div>

          <p className="pay-detail__note">
            <Icon name="lock" size={14} />
            <span>
              Le numéro complet et le CVC ne sont <b>jamais enregistrés</b> :
              seuls le réseau et les quatre derniers chiffres sont conservés, pour que vous
              reconnaissiez ce paiement dans votre historique.
            </span>
          </p>
        </div>
      )}
    </>
  )
}
