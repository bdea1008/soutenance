/**
 * Moyens de paiement : ce qui relève des données et non de l'affichage (§7.4).
 *
 * Séparé des composants comme `utils/roles.js` et `utils/format.js` le sont :
 * la liste des fournisseurs, la forme de l'état de saisie et sa traduction en
 * champs d'API sont utilisées par plusieurs écrans, dont certains n'affichent
 * aucun formulaire.
 */

/**
 * Fournisseurs supportés, alignés sur `App\Enums\PaymentProvider`.
 *
 * L'API les annonce (`GET /subscription-plans`) et reste la source de vérité
 * quand elle est déjà chargée. Cette liste sert aux écrans qui n'ont aucune
 * autre raison de l'appeler — trois valeurs fixes ne valent pas un aller-retour
 * réseau sur une connexion limitée (§14).
 */
export const PAYMENT_PROVIDERS = [
  { value: 'wave', label: 'Wave' },
  { value: 'orange_money', label: 'Orange Money' },
  { value: 'card', label: 'Carte bancaire' },
]

/** Le moyen s'identifie-t-il par un numéro de téléphone ? (cf. `PaymentProvider::kind()`) */
export function isMobileMoney(provider) {
  return provider === 'wave' || provider === 'orange_money'
}

export function providerLabel(providers, value) {
  return providers.find((p) => p.value === value)?.label ?? 'Mobile Money'
}

/** État initial du formulaire, pré-rempli avec ce qu'on sait déjà du payeur. */
export function emptyInstrument(user, providers = []) {
  return {
    provider: providers[0]?.value ?? 'wave',
    // Le numéro du compte est proposé par défaut : c'est celui que la plupart
    // des gens utiliseront, et il a déjà été vérifié à l'inscription.
    accountPhone: user?.phone ?? '',
    useOtherPhone: false,
    otherPhone: '',
    cardNumber: '',
    cardExpiry: '',
    cardCvc: '',
    cardHolder: user?.name ?? '',
  }
}

/**
 * Traduit l'état du formulaire en champs d'API.
 *
 * Seuls les champs du moyen retenu sont envoyés : inutile de transmettre un
 * numéro de carte à demi saisi parce que l'utilisateur a changé d'avis en
 * cours de route.
 */
export function instrumentPayload(instrument) {
  if (instrument.provider === 'card') {
    return {
      provider: instrument.provider,
      card_number: instrument.cardNumber,
      card_expiry: instrument.cardExpiry,
      card_cvc: instrument.cardCvc,
      card_holder: instrument.cardHolder,
    }
  }

  return {
    provider: instrument.provider,
    phone: instrument.useOtherPhone ? instrument.otherPhone : instrument.accountPhone,
  }
}

/**
 * Le moyen choisi, en une ligne, pour le récapitulatif de paiement.
 *
 * Suit ce que le serveur enregistrera (`Payment::instrumentLabel()`) : le
 * numéro débité, ou le réseau et les quatre derniers chiffres. Tant que la
 * saisie est incomplète, on annonce le moyen sans prétendre en connaître les
 * coordonnées.
 */
export function instrumentLabel(instrument, providers = []) {
  const name = providerLabel(providers, instrument.provider)

  if (instrument.provider === 'card') {
    const digits = instrument.cardNumber.replace(/\D/g, '')

    return digits.length >= 4
      ? `${cardBrand(digits) ?? 'Carte'} •••• ${digits.slice(-4)}`
      : name
  }

  const phone = instrument.useOtherPhone ? instrument.otherPhone : instrument.accountPhone

  return phone ? `${name} · ${phone}` : name
}

/**
 * Forme courte du réseau, pour l'étiquette posée dans le champ de saisie.
 *
 * « American Express » y tiendrait mal — le numéro passerait dessous sur un
 * écran étroit — et « Amex » est de toute façon la forme que porte la carte
 * elle-même. Le nom complet reste celui qui est enregistré.
 */
export function shortBrand(brand) {
  return brand === 'American Express' ? 'Amex' : brand
}

/**
 * Réseau déduit des premiers chiffres (norme ISO/IEC 7812).
 *
 * Purement cosmétique : c'est `App\Support\PaymentInstrument::brandOf()` qui
 * fait foi et dont la valeur sera enregistrée. Ici il s'agit seulement de
 * confirmer à l'utilisateur, pendant qu'il tape, que sa carte est reconnue.
 */
export function cardBrand(number) {
  const d = String(number).replace(/\D/g, '')

  if (!d) return null
  if (/^4/.test(d)) return 'Visa'
  if (/^(5[1-5]|2(2[2-9]|[3-6]\d|7[01])\d)/.test(d)) return 'Mastercard'
  if (/^3[47]/.test(d)) return 'American Express'
  if (/^(6011|65|64[4-9])/.test(d)) return 'Discover'
  if (/^(50|6)/.test(d)) return 'Maestro'

  return null
}
