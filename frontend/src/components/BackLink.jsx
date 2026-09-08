import { useLocation, useNavigate } from 'react-router-dom'

/**
 * Retour des pages intérieures — celles qu'on atteint depuis une autre page et
 * non depuis la barre de navigation.
 *
 * Deux usages, et un seul principe : **le libellé ne ment jamais**.
 *   - `to` donné : on va exactement là, et le libellé nomme la destination
 *     (« ← Mes projets »). C'est le cas courant, parce qu'une page intérieure
 *     a presque toujours un parent évident.
 *   - `to` absent : retour au cran précédent de l'historique, avec un libellé
 *     générique (« ← Retour »). Réservé aux pages atteintes depuis n'importe
 *     où, comme la boîte de notifications, où nommer une destination serait
 *     faux une fois sur deux.
 *
 * Distinct de `BackButton`, qui sert les écrans d'identification : ceux-là
 * n'ont ni barre de navigation ni pied de page, leur retour est la seule issue
 * et il gère en plus le recul d'étape en étape de l'inscription.
 */
export default function BackLink({ to = null, label = 'Retour' }) {
  const navigate = useNavigate()
  const location = useLocation()

  function goBack() {
    if (to) {
      navigate(to)
      return
    }

    // `key` vaut « default » sur la première entrée d'historique (lien direct,
    // rafraîchissement) : il n'y a rien derrière, on va à l'accueil.
    navigate(location.key === 'default' ? '/' : -1)
  }

  return (
    <button type="button" className="back-link" onClick={goBack}>
      ← {label}
    </button>
  )
}
