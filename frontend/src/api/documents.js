import client from './client'

/**
 * Télécharge une pièce KYC.
 * Le fichier est servi derrière l'authentification JWT : un lien `href` direct
 * ne fonctionnerait pas (l'en-tête Authorization ne serait pas transmis).
 * On récupère donc le binaire puis on déclenche le téléchargement localement.
 */
export async function downloadDocument(document_) {
  const res = await client.get(`/documents/${document_.id}/download`, { responseType: 'blob' })

  const url = URL.createObjectURL(res.data)
  const link = document.createElement('a')
  link.href = url
  link.download = document_.original_name || `document-${document_.id}`
  document.body.appendChild(link)
  link.click()
  link.remove()
  URL.revokeObjectURL(url)
}
