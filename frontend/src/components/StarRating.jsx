import Icon from './Icon'

/**
 * Note en étoiles, 1 à 5.
 *
 * Une seule icône (`star`) réutilisée pour les deux états : remplie (or) pour
 * les crans atteints, atténuée (couleur de bordure) au-delà — pas besoin d'un
 * second tracé « étoile creuse » pour cette distinction.
 *
 * Sans `onChange`, purement décorative (moyenne affichée) ; avec `onChange`,
 * un groupe de cinq boutons — la cible tactile la plus simple pour 1 à 5.
 */
export default function StarRating({ value, onChange, size = 18 }) {
  const stars = [1, 2, 3, 4, 5]
  const interactive = typeof onChange === 'function'
  const rounded = Math.round(value || 0)

  if (!interactive) {
    return (
      <span className="star-rating" role="img" aria-label={`${value ?? 0} sur 5`}>
        {stars.map((n) => (
          <Icon
            key={n} name="star" size={size}
            className={`star-rating__star ${n <= rounded ? 'star-rating__star--on' : ''}`}
          />
        ))}
      </span>
    )
  }

  return (
    <span className="star-rating" role="radiogroup" aria-label="Votre note">
      {stars.map((n) => (
        <button
          key={n}
          type="button"
          role="radio"
          aria-checked={n === value}
          aria-label={`${n} étoile${n > 1 ? 's' : ''}`}
          className={`star-rating__star star-rating__star--btn ${n <= value ? 'star-rating__star--on' : ''}`}
          onClick={() => onChange(n)}
        >
          <Icon name="star" size={size} />
        </button>
      ))}
    </span>
  )
}
