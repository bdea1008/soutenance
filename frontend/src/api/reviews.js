import client from './client'

/** Avis d'un projet (§2, extension d'« Investir dans un projet »). */
export function fetchReviews(projectId, params = {}) {
  return client.get(`/projects/${projectId}/reviews`, { params }).then((res) => res.data)
}

/** Dépose ou met à jour son propre avis — une seule note par investisseur et par projet. */
export function submitReview(projectId, { rating, comment }) {
  return client.post(`/projects/${projectId}/reviews`, { rating, comment }).then((res) => res.data)
}

/** Retire son propre avis. */
export function deleteReview(projectId) {
  return client.delete(`/projects/${projectId}/reviews`).then((res) => res.data)
}
