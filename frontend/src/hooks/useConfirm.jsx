import { useCallback, useRef, useState } from 'react'
import ConfirmDialog from '../components/ConfirmDialog'

/**
 * Confirmation en fenêtre, appelée comme `window.confirm` l'était.
 *
 * `window.confirm` avait un mérite : il s'écrivait sur une ligne, au milieu du
 * code qu'il protégeait. Le remplacer par un composant piloté par un état
 * aurait dispersé chaque confirmation en trois endroits — l'état, le
 * gestionnaire, le rendu — et pour une douzaine d'appels, personne n'aurait
 * tenu la discipline.
 *
 * Ce crochet rend une promesse : l'appel reste sur une ligne, la fenêtre est
 * celle de l'application.
 *
 *     const { confirm, confirmDialog } = useConfirm()
 *     …
 *     if (!await confirm({ title: 'Supprimer ce projet ?', confirmTone: 'danger' })) return
 *     …
 *     return (<>{contenu}{confirmDialog}</>)
 *
 * Une seule fenêtre à la fois par page, ce qui est exactement le besoin : une
 * confirmation bloque, on n'en empile pas deux.
 *
 * Avec l'option `prompt`, la promesse rend **la valeur saisie** ou `null` si
 * l'on renonce — exactement la convention de `window.prompt`, pour que les
 * appels qui en venaient n'aient rien à changer à leur logique.
 */
export default function useConfirm() {
  const [request, setRequest] = useState(null)
  const resolve = useRef(null)

  const confirm = useCallback((options) => new Promise((done) => {
    resolve.current = done
    setRequest(options)
  }), [])

  const settle = useCallback((answer) => {
    setRequest(null)
    resolve.current?.(answer)
    resolve.current = null
  }, [])

  const confirmDialog = (
    <ConfirmDialog
      open={Boolean(request)}
      {...(request ?? {})}
      // Avec une saisie : la valeur, ou `null` si l'on renonce.
      // Sans : le booléen habituel.
      onConfirm={(answer) => settle(request?.prompt ? answer : true)}
      onCancel={() => settle(request?.prompt ? null : false)}
    />
  )

  return { confirm, confirmDialog }
}
