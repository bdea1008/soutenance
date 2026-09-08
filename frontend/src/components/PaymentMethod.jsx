import Icon from './Icon'
import waveMark from '../assets/providers/wave.png'
import orangeMoneyMark from '../assets/providers/orange-money-mark.png'
import orangeMoneyLockup from '../assets/providers/orange-money.png'

/**
 * Moyens de paiement Mobile Money (§7.4).
 *
 * Les visuels vivent ici et nulle part ailleurs : les fournisseurs sont
 * annoncés par l'API (`/subscription-plans`), mais leurs logos sont des
 * fichiers de marque, pas des données — les faire transiter par le réseau
 * n'aurait servi à rien. Cette table fait le lien entre les deux.
 *
 * Deux visuels par fournisseur, parce qu'un logo ne se comporte pas pareil
 * selon qu'un libellé l'accompagne ou non :
 * - `mark` : le pictogramme seul, quand le nom est écrit juste à côté ;
 * - `lockup` : le logo complet (pictogramme + mot-symbole), quand il est seul
 *   à porter l'identification, dans une frise de moyens acceptés.
 *
 * Le carré cyan de Wave *est* sa marque — il n'est pas détouré, contrairement
 * au fond blanc d'Orange Money qui, lui, n'était qu'un fond de fichier.
 */
const PROVIDER_ART = {
  wave: {
    mark: waveMark,
    lockup: waveMark,
    hint: 'Application Wave',
    // Le carré cyan doit être arrondi comme une icône d'application, sinon il
    // se lit comme une vignette mal détourée à côté du logo détouré d'Orange.
    boxed: true,
  },
  orange_money: {
    mark: orangeMoneyMark,
    lockup: orangeMoneyLockup,
    hint: 'Compte Orange Money',
    boxed: false,
  },
  // La carte n'a pas de logo de marque : le réseau (Visa, Mastercard…) ne se
  // connaît qu'une fois le numéro saisi, et il est alors annoncé dans le champ
  // lui-même. Un pictogramme générique dit le moyen sans rien préjuger.
  card: {
    icon: 'card',
    hint: 'Visa, Mastercard, GIM-UEMOA',
  },
}

/**
 * Logo d'un fournisseur, à hauteur fixe et largeur libre — comme dans une
 * rangée de moyens de paiement acceptés. Forcer un carré déformerait le
 * mot-symbole d'Orange Money, presque quatre fois plus large que haut.
 */
export function ProviderLogo({ provider, label, variant = 'mark', height = 24 }) {
  const art = PROVIDER_ART[provider]

  // Moyen sans logo de marque (la carte) ou fournisseur ajouté côté API sans
  // visuel ici : un pictogramme du jeu maison, à la même hauteur que les logos.
  if (!art?.mark) {
    return <Icon name={art?.icon ?? 'wallet'} size={height} title={label} />
  }

  return (
    <img
      src={variant === 'lockup' ? art.lockup : art.mark}
      alt={label ?? ''}
      className={`pay-logo${art.boxed ? ' pay-logo--boxed' : ''}`}
      style={{ height }}
    />
  )
}

/**
 * Choix du moyen de paiement, en vignettes plutôt qu'en menu déroulant.
 *
 * Un `<select>` cachait les deux seules options derrière un clic et ne
 * montrait aucun logo — c'est pourtant le logo qui est reconnu, pas le nom
 * écrit. Ce sont de vrais boutons radio : la navigation au clavier, la
 * sélection par les flèches et l'annonce vocale restent celles du navigateur.
 */
export default function PaymentMethodPicker({
  providers,
  value,
  onChange,
  name = 'provider',
  disabled = false,
}) {
  return (
    <div className="pay-options" role="radiogroup" aria-label="Moyen de paiement">
      {providers.map((p) => {
        const selected = value === p.value
        const hint = PROVIDER_ART[p.value]?.hint

        return (
          <label
            key={p.value}
            className={`pay-option${selected ? ' pay-option--on' : ''}`}
          >
            <input
              type="radio"
              name={name}
              value={p.value}
              checked={selected}
              onChange={() => onChange(p.value)}
              disabled={disabled}
              className="pay-option__input"
            />
            <span className="pay-option__logo">
              <ProviderLogo provider={p.value} height={26} />
            </span>
            <span className="pay-option__body">
              <span className="pay-option__name">{p.label}</span>
              {hint && <span className="pay-option__hint">{hint}</span>}
            </span>
            {/* Coche de sélection : la bordure seule se remarque mal sur un
                fond ivoire, et deux vignettes côte à côte doivent se
                départager d'un coup d'œil. */}
            <span className="pay-option__check" aria-hidden="true">
              <Icon name="check" size={18} />
            </span>
          </label>
        )
      })}
    </div>
  )
}

/**
 * Frise « moyens acceptés » — rassure avant même d'ouvrir le paiement.
 * Logos complets : ici aucun libellé ne les accompagne.
 */
export function AcceptedMethods({ providers, label = 'Paiement par' }) {
  if (!providers?.length) return null

  return (
    <p className="pay-accepted">
      {/* Libellé facultatif : dans un encart étroit, les logos se suffisent et
          « Paiement par » mangerait la ligne. */}
      {label && <span className="pay-accepted__label">{label}</span>}
      {providers.map((p) => (
        <ProviderLogo key={p.value} provider={p.value} label={p.label} variant="lockup" height={20} />
      ))}
    </p>
  )
}
