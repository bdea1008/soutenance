import { useLocation, useNavigate } from 'react-router-dom'

/**
 * Retour des écrans d'identification.
 *
 * Ces pages n'ont ni barre de navigation ni pied de page — rien ne doit
 * détourner du formulaire — donc ce bouton est la seule issue autre que
 * s'identifier. Il ne peut pas être un cul-de-sac, d'où les deux garde-fous
 * ci-dessous plutôt qu'un `navigate(-1)` sec.
 *
 * `onBack` prend la main quand il y a un cran en arrière *dans* la page :
 * l'inscription s'en sert pour revenir à l'étape précédente. Sans lui, on
 * quitte la page. Dans les deux cas le bouton dit la même chose — un cran en
 * arrière — et c'est le contexte qui décide de quel cran il s'agit.
 */
export default function BackButton({ label = 'Retour', onBack = null }) {
  const navigate = useNavigate()
  const location = useLocation()

  function goBack() {
    if (onBack) {
      onBack()
      return
    }

    // Renvoyé ici par ProtectedRoute : revenir en arrière ramènerait sur la
    // page protégée, qui nous renverrait aussitôt sur la connexion.
    if (location.state?.from) {
      navigate('/')
      return
    }

    // `key` vaut « default » sur la première entrée d'historique (lien direct,
    // rafraîchissement) : il n'y a rien derrière, on va à l'accueil.
    if (location.key === 'default') {
      navigate('/')
      return
    }

    navigate(-1)
  }

  return (
    <button type="button" className="auth-back" onClick={goBack}>
      ← {label}
    </button>
  )
}
