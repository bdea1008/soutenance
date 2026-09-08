import client from './client'

/**
 * Charge une image servie derrière l'authentification JWT.
 * Un `<img src>` direct ne fonctionnerait pas : le navigateur n'enverrait pas
 * l'en-tête Authorization. On récupère donc le binaire et on en fait une URL
 * locale — à révoquer par l'appelant quand l'image n'est plus affichée.
 */
export async function fetchProtectedImage(url) {
  // Les URLs renvoyées par l'API sont absolues (/api/...), le client a déjà /api en base.
  const res = await client.get(url.replace(/^\/api/, ''), { responseType: 'blob' })
  return URL.createObjectURL(res.data)
}

/** Journal d'avancement d'un projet. */
export function fetchReports(projectId, params = {}) {
  return client.get(`/projects/${projectId}/reports`, { params }).then((res) => res.data)
}

/**
 * Construit le corps multipart d'un rapport. Les photos sont facultatives et
 * s'ajoutent aux précédentes lors d'une modification.
 */
export function reportFormData({ title, description, progress_percentage, reported_at, photos }) {
  const payload = new FormData()
  if (title !== undefined) payload.append('title', title)
  if (description !== undefined) payload.append('description', description ?? '')
  if (progress_percentage !== undefined) payload.append('progress_percentage', progress_percentage)
  if (reported_at) payload.append('reported_at', reported_at)
  for (const photo of photos ?? []) payload.append('photos[]', photo)
  return payload
}
