import client, { errorMessage } from '../api/client'

/**
 * Publication d'un projet, et description de ce qui la bloque.
 *
 * Le bouton « Publier » est proposé sur tout brouillon, même incomplet : c'est
 * en le pressant que le promoteur apprend ce qui lui manque, et l'apprendre ne
 * doit pas être un cul-de-sac. Ce module ne fait qu'établir le constat — c'est
 * `ConfirmDialog` qui l'affiche, et la page qui décide d'y aller ou non.
 *
 * Rien n'est rendu ici volontairement : une fonction utilitaire qui ouvrirait
 * elle-même une fenêtre (`window.confirm`, comme avant) impose son apparence à
 * toute l'application et échappe à la mise en forme.
 *
 * @param {object} project
 * @returns {Promise<{ok: boolean, message: string, blocker: object|null}>}
 */
export async function attemptPublish(project) {
  try {
    const res = await client.post(`/projects/${project.id}/publish`)
    return { ok: true, message: res.data.message, blocker: null }
  } catch (err) {
    const data = err.response?.data ?? {}
    return {
      ok: false,
      message: errorMessage(err, 'Publication impossible.'),
      blocker: describeBlocker(data, project),
    }
  }
}

/**
 * Traduit un refus de l'API en fenêtre de confirmation : ce qui bloque, où le
 * lever. `null` quand le refus n'appelle aucune action (statut incompatible,
 * panne réseau) — la page se contente alors d'afficher le message.
 */
function describeBlocker(data, project) {
  if (data.code === 'dossier_incomplete') {
    return {
      tone: 'warning',
      icon: 'alert',
      eyebrow: 'Publication en attente',
      title: 'Le dossier de financement est incomplet',
      subtitle: project.title,
      message: 'Chaque projet mis en financement doit présenter ses pièces justificatives. '
        + 'Il en manque encore, ou elles attendent la validation d’un administrateur.',
      items: data.missing ?? [],
      progress: data.progress ?? null,
      confirmLabel: 'Compléter le dossier',
      to: `/promoteur/projets/${project.id}/dossier`,
    }
  }

  if (data.code === 'subscription_required' || data.code === 'quota_exceeded') {
    return {
      tone: 'info',
      icon: 'wallet',
      eyebrow: 'Abonnement',
      title: data.code === 'quota_exceeded'
        ? 'Votre palier ne permet pas d’autres projets en ligne'
        : 'Un abonnement actif est requis',
      subtitle: project.title,
      message: data.message,
      confirmLabel: 'Voir mon abonnement',
      to: '/promoteur/abonnement',
    }
  }

  // Dossier de l'opérateur incomplet : le middleware `kyc.verified` répond sans
  // code applicatif, on le reconnaît à la présence du statut dans la réponse.
  if (data.kyc_status !== undefined) {
    return {
      tone: 'warning',
      icon: 'lock',
      eyebrow: 'Vérification',
      title: 'Votre dossier promoteur n’est pas encore validé',
      subtitle: project.title,
      message: 'Avant de mettre un projet en financement, votre propre dossier doit être complet '
        + 'et validé. Il ne vous sera demandé qu’une seule fois, pour tous vos projets.',
      confirmLabel: 'Compléter mon dossier',
      to: '/verification',
    }
  }

  return null
}
