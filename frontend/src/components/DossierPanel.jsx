import { useMemo, useRef, useState } from 'react'
import client, { errorMessage } from '../api/client'
import { downloadDocument } from '../api/documents'
import Icon from './Icon'
import useConfirm from '../hooks/useConfirm'

/** Pastille de couleur selon l'état d'une pièce. */
const DOC_TONE = {
  approved: 'badge--risk-low',
  pending: 'badge--risk-medium',
  rejected: 'badge--risk-high',
  expired: 'badge--risk-high',
}

function formatDate(value) {
  if (!value) return '—'
  return new Date(value).toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' })
}

/**
 * Panneau de dossier documentaire : checklist, dépôt et pièces déposées.
 *
 * Le même composant sert aux deux dossiers, parce qu'ils posent la même
 * question et suivent la même file de modération :
 *   - le dossier de l'opérateur (`/me/documents`), déposé une seule fois ;
 *   - le dossier d'une opération (`/projects/{id}/dossier`), refait à chaque
 *     projet — c'est alors `projectId` qui rattache le dépôt.
 *
 * Le chargement des données reste au parent : lui seul sait quelle page il est
 * et quoi faire ensuite (rafraîchir l'utilisateur, revenir à la liste…).
 */
export default function DossierPanel({ dossier, projectId = null, onChanged, readOnly = false }) {
  const fileInput = useRef(null)

  const [type, setType] = useState('')
  const [issuedAt, setIssuedAt] = useState('')
  const [file, setFile] = useState(null)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')
  const [notice, setNotice] = useState('')
  const { confirm, confirmDialog } = useConfirm()

  const { checklist, documents, accepted } = dossier

  // Options de dépôt : les pièces attendues, plus un fourre-tout « Autre » sur
  // un dossier de projet (le serveur l'y accepte, il ne fait partie d'aucune
  // ligne de checklist puisqu'il n'y a rien à en attendre).
  const options = useMemo(() => {
    const base = checklist.map((c) => ({ value: c.type, label: c.label, validity: c.validity_months }))
    return projectId ? [...base, { value: 'other', label: 'Autre pièce', validity: null }] : base
  }, [checklist, projectId])

  // Première pièce encore attendue : c'est celle que le déposant cherche.
  const suggested = checklist.find((c) => c.required && !c.satisfied)?.type
  const selected = type || suggested || options[0]?.value || ''
  const needsIssueDate = options.find((o) => o.value === selected)?.validity != null

  async function submit(e) {
    e.preventDefault()
    if (!file) return
    setBusy(true)
    setError('')
    setNotice('')

    const payload = new FormData()
    payload.append('file', file)
    payload.append('type', selected)
    if (projectId) payload.append('project_id', projectId)
    if (needsIssueDate && issuedAt) payload.append('issued_at', issuedAt)

    try {
      const res = await client.post('/me/documents', payload)
      setNotice(res.data.message)
      setFile(null)
      setIssuedAt('')
      if (fileInput.current) fileInput.current.value = ''
      await onChanged()
    } catch (err) {
      setError(errorMessage(err, 'Dépôt impossible.'))
    } finally {
      setBusy(false)
    }
  }

  async function remove(doc) {
    const confirmed = await confirm({
      tone: 'danger',
      icon: 'ban',
      eyebrow: 'Dossier',
      title: 'Retirer cette pièce ?',
      subtitle: doc.type_label,
      message: 'Le fichier sera supprimé et la ligne correspondante repassera « à déposer ». '
        + 'Il faudra en déposer une nouvelle pour que le dossier aboutisse.',
      confirmLabel: 'Retirer la pièce',
      confirmTone: 'danger',
    })

    if (!confirmed) return
    setError('')
    setNotice('')
    try {
      const res = await client.delete(`/me/documents/${doc.id}`)
      setNotice(res.data.message)
      await onChanged()
    } catch (err) {
      setError(errorMessage(err, 'Suppression impossible.'))
    }
  }

  async function download(doc) {
    try {
      await downloadDocument(doc)
    } catch {
      setError('Téléchargement impossible.')
    }
  }

  const { required, satisfied } = dossier.progress

  return (
    <>
      {confirmDialog}
      {error && <div className="alert alert--error">{error}</div>}
      {notice && <div className="alert alert--success">{notice}</div>}

      {/* --- Pièces attendues ---------------------------------------------- */}
      <h2 style={{ fontSize: '1.3rem' }}>
        Pièces attendues <span className="text-muted">({satisfied}/{required} validées)</span>
      </h2>

      <div className="card kyc-checklist">
        {checklist.map((item) => (
          <div key={item.type} className="kyc-check">
            <span className={`kyc-check__mark${item.satisfied ? ' kyc-check__mark--done' : ''}`}>
              {item.satisfied ? '✓' : '•'}
            </span>
            <span className="kyc-check__label">
              {item.label}
              {!item.required && <span className="dossier-check__optional">facultatif</span>}
              {item.hint && <small className="dossier-check__hint">{item.hint}</small>}
              {item.satisfied && item.expires_at && (
                <small className="dossier-check__hint">Valable jusqu’au {formatDate(item.expires_at)}.</small>
              )}
            </span>
            <span className={`badge ${DOC_TONE[item.status] ?? ''}`}>{item.status_label}</span>
          </div>
        ))}
      </div>

      {/* --- Dépôt ---------------------------------------------------------- */}
      {!readOnly && (
        <>
          <h2 style={{ fontSize: '1.3rem', marginTop: '2.5rem' }}>Déposer une pièce</h2>
          <form onSubmit={submit} className="card" style={{ padding: '1.5rem', maxWidth: 620 }}>
            <div className="field">
              <label htmlFor="dossier-type">Nature de la pièce</label>
              <select id="dossier-type" value={selected} onChange={(e) => setType(e.target.value)} required>
                {options.map((o) => (
                  <option key={o.value} value={o.value}>{o.label}</option>
                ))}
              </select>
            </div>

            {/* Une pièce datée ne vaut que quelques mois : sans sa date
                d'émission, impossible de savoir si elle est encore recevable. */}
            {needsIssueDate && (
              <div className="field">
                <label htmlFor="dossier-issued">Date d’émission du document</label>
                <input
                  id="dossier-issued" type="date" required
                  value={issuedAt}
                  max={new Date().toISOString().slice(0, 10)}
                  onChange={(e) => setIssuedAt(e.target.value)}
                />
                <small className="text-muted">
                  Cette pièce est valable {options.find((o) => o.value === selected)?.validity} mois
                  à compter de son émission.
                </small>
              </div>
            )}

            <div className="field">
              <label htmlFor="dossier-file">Fichier</label>
              <input
                id="dossier-file" type="file" ref={fileInput} required
                accept=".jpg,.jpeg,.png,.pdf"
                onChange={(e) => setFile(e.target.files[0] ?? null)}
              />
              <small className="text-muted">
                Formats acceptés : {accepted.formats.join(', ').toUpperCase()} — {accepted.max_mb} Mo maximum.
              </small>
            </div>

            <button className="btn btn--primary" disabled={busy || !file}>
              {busy ? 'Envoi…' : 'Déposer la pièce'}
            </button>
          </form>
        </>
      )}

      {/* --- Pièces déposées ------------------------------------------------ */}
      <h2 style={{ fontSize: '1.3rem', marginTop: '2.5rem' }}>Pièces déposées</h2>
      {documents.length === 0 ? (
        <p className="text-muted">Aucune pièce déposée pour le moment.</p>
      ) : (
        <div className="card">
          {documents.map((doc) => (
            <div key={doc.id} className="promo-item">
              <div className="promo-item__main">
                <div className="promo-item__title">
                  <b>{doc.type_label}</b>
                  <span className={`badge ${DOC_TONE[doc.expired ? 'expired' : doc.status] ?? ''}`}>
                    {doc.expired ? 'Expirée' : doc.status_label}
                  </span>
                </div>
                <div className="text-muted" style={{ fontSize: '0.85rem' }}>
                  {doc.original_name} · déposée le {formatDate(doc.created_at)}
                  {doc.reviewed_at && ` · examinée le ${formatDate(doc.reviewed_at)}`}
                  {doc.expires_at && ` · ${doc.expired ? 'expirée' : 'valable'} jusqu’au ${formatDate(doc.expires_at)}`}
                </div>
                {doc.review_note && (
                  <div className="alert alert--error" style={{ marginTop: '0.6rem', marginBottom: 0 }}>
                    Motif : {doc.review_note}
                  </div>
                )}
                {doc.expired && (
                  <div className="alert alert--warning" style={{ marginTop: '0.6rem', marginBottom: 0 }}>
                    <Icon name="alert" /> Cette pièce a dépassé sa durée de validité : déposez-en une plus récente.
                  </div>
                )}
              </div>
              <div className="promo-item__actions">
                <button className="btn btn--ghost" onClick={() => download(doc)}>Télécharger</button>
                {!readOnly && doc.status !== 'approved' && (
                  <button className="btn btn--ghost promo-item__danger" onClick={() => remove(doc)}>
                    Retirer
                  </button>
                )}
              </div>
            </div>
          ))}
        </div>
      )}
    </>
  )
}
