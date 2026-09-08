import { useCallback, useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import AdminNav from '../../components/AdminNav'
import client, { errorMessage } from '../../api/client'
import { downloadDocument } from '../../api/documents'

const FILTERS = [
  { value: 'pending', label: 'En attente' },
  { value: 'approved', label: 'Validées' },
  { value: 'rejected', label: 'Rejetées' },
]

const TONE = {
  approved: 'badge--risk-low',
  pending: 'badge--risk-medium',
  rejected: 'badge--risk-high',
}

function formatDate(value) {
  if (!value) return '—'
  return new Date(value).toLocaleDateString('fr-FR', {
    day: 'numeric', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit',
  })
}

export default function AdminDocuments() {
  const [documents, setDocuments] = useState([])
  const [stats, setStats] = useState({ pending: 0, approved: 0, rejected: 0 })
  const [filter, setFilter] = useState('pending')
  const [notes, setNotes] = useState({})
  const [loading, setLoading] = useState(true)
  const [busyId, setBusyId] = useState(null)
  const [error, setError] = useState('')
  const [notice, setNotice] = useState('')

  const load = useCallback(() => {
    setLoading(true)
    Promise.all([
      client.get('/admin/documents', { params: { status: filter, per_page: 50 } }),
      client.get('/admin/documents/stats'),
    ])
      .then(([queue, counters]) => {
        setDocuments(queue.data.data)
        setStats(counters.data)
      })
      .catch((err) => setError(errorMessage(err, 'Impossible de charger la file de modération.')))
      .finally(() => setLoading(false))
  }, [filter])

  useEffect(() => { load() }, [load])

  async function review(doc, decision) {
    const note = notes[doc.id]?.trim() || ''

    if (decision === 'reject' && !note) {
      setError('Un motif est requis pour rejeter une pièce.')
      return
    }

    setBusyId(doc.id)
    setError('')
    setNotice('')
    try {
      const res = await client.post(`/admin/documents/${doc.id}/review`, {
        decision,
        note: note || undefined,
      })
      setNotice(`${res.data.message} — KYC de ${doc.owner.name} : ${res.data.user_kyc_status_label}.`)
      setNotes((prev) => ({ ...prev, [doc.id]: '' }))
      load()
    } catch (err) {
      setError(errorMessage(err, 'Décision impossible.'))
    } finally {
      setBusyId(null)
    }
  }

  async function download(doc) {
    try {
      await downloadDocument(doc)
    } catch {
      setError('Téléchargement impossible.')
    }
  }

  return (
    <div className="container">
      <AdminNav
        title="Vérification des pièces KYC"
        subtitle="Chaque décision met à jour automatiquement le statut de vérification de l’utilisateur."
      />

      <div className="grid grid--3" style={{ marginBottom: '1.5rem' }}>
        <div className="stat"><div className="stat__value">{stats.pending}</div><div className="stat__label">En attente</div></div>
        <div className="stat"><div className="stat__value">{stats.approved}</div><div className="stat__label">Validées</div></div>
        <div className="stat"><div className="stat__value">{stats.rejected}</div><div className="stat__label">Rejetées</div></div>
      </div>

      {error && <div className="alert alert--error">{error}</div>}
      {notice && <div className="alert alert--success">{notice}</div>}

      <div style={{ display: 'flex', gap: '0.5rem', flexWrap: 'wrap', marginBottom: '1.5rem' }}>
        {FILTERS.map((f) => (
          <button
            key={f.value}
            className={`btn ${filter === f.value ? 'btn--primary' : 'btn--ghost'}`}
            onClick={() => setFilter(f.value)}
          >
            {f.label}
          </button>
        ))}
      </div>

      {loading ? (
        <div className="spinner" />
      ) : documents.length === 0 ? (
        <div className="card" style={{ padding: '2rem', textAlign: 'center' }}>
          <p className="text-muted" style={{ margin: 0 }}>
            {filter === 'pending' ? 'Aucune pièce en attente. File vide.' : 'Aucune pièce dans cette catégorie.'}
          </p>
        </div>
      ) : (
        <div className="stack" style={{ paddingBottom: '3rem' }}>
          {documents.map((doc) => (
            <div key={doc.id} className="card review-item">
              <div className="review-item__head">
                <div>
                  <div className="promo-item__title">
                    <b>{doc.type_label}</b>
                    <span className={`badge ${TONE[doc.status] ?? ''}`}>{doc.status_label}</span>
                  </div>
                  <div className="text-muted" style={{ fontSize: '0.88rem' }}>
                    {/* La fiche du titulaire donne le contexte de la décision :
                        pièces déjà validées, projets portés, historique. */}
                    <Link to={`/admin/utilisateurs/${doc.owner.id}`}>{doc.owner.name}</Link>
                    {' · '}{doc.owner.email} · {doc.owner.role_label}
                  </div>
                  <div className="text-muted" style={{ fontSize: '0.82rem' }}>
                    {doc.context_label} · {doc.original_name} · déposée le {formatDate(doc.created_at)}
                  </div>
                </div>
                <div style={{ textAlign: 'right' }}>
                  <span className={`badge ${doc.owner.kyc_status === 'verified' ? 'badge--risk-low' : ''}`}>
                    KYC : {doc.owner.kyc_status}
                  </span>
                </div>
              </div>

              {doc.review_note && (
                <p className="text-muted" style={{ fontSize: '0.85rem' }}>
                  Motif enregistré : {doc.review_note}
                  {doc.reviewer && ` (${doc.reviewer})`}
                </p>
              )}

              <div className="review-item__actions">
                <button className="btn btn--ghost" onClick={() => download(doc)}>
                  Consulter la pièce
                </button>

                {doc.status === 'pending' && (
                  <>
                    <input
                      className="review-item__note"
                      placeholder="Motif (obligatoire en cas de rejet)"
                      value={notes[doc.id] ?? ''}
                      onChange={(e) => setNotes((prev) => ({ ...prev, [doc.id]: e.target.value }))}
                    />
                    <button
                      className="btn btn--primary"
                      disabled={busyId === doc.id}
                      onClick={() => review(doc, 'approve')}
                    >
                      Valider
                    </button>
                    <button
                      className="btn btn--ghost promo-item__danger"
                      disabled={busyId === doc.id}
                      onClick={() => review(doc, 'reject')}
                    >
                      Rejeter
                    </button>
                  </>
                )}
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  )
}
