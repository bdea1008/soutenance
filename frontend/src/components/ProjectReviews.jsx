import { useEffect, useState } from 'react'
import StarRating from './StarRating'
import { deleteReview, fetchReviews, submitReview } from '../api/reviews'
import { errorMessage } from '../api/client'
import { useAuth } from '../context/AuthContext'
import useConfirm from '../hooks/useConfirm'

function formatDate(value) {
  if (!value) return '—'
  return new Date(value).toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' })
}

/**
 * Avis d'investisseurs sur un projet (§2, extension d'« Investir dans un
 * projet »). Widget autonome : il charge et gère lui-même ses données,
 * `ProjectDetail` ne lui passe que l'identifiant du projet.
 *
 * `canReview` ne fait que décider d'afficher le formulaire — l'API est seule
 * juge de qui a réellement investi (403 sinon), la même règle que côté
 * serveur n'est pas dupliquée ici.
 */
export default function ProjectReviews({ projectId, canReview }) {
  const { user } = useAuth()
  const [reviews, setReviews] = useState([])
  const [summary, setSummary] = useState(null)
  const [loading, setLoading] = useState(true)
  const [form, setForm] = useState(null) // { rating, comment } pendant l'édition
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState('')
  const { confirm, confirmDialog } = useConfirm()

  function load() {
    fetchReviews(projectId)
      .then((res) => {
        setReviews(res.data)
        setSummary(res.meta_summary)
      })
      .catch(() => {})
      .finally(() => setLoading(false))
  }

  useEffect(() => { load() }, [projectId])

  const mine = reviews.find((r) => r.investor?.id === user.id)

  async function submit(e) {
    e.preventDefault()
    setSaving(true)
    setError('')
    try {
      await submitReview(projectId, form)
      setForm(null)
      load()
    } catch (err) {
      setError(errorMessage(err, 'Publication impossible.'))
    } finally {
      setSaving(false)
    }
  }

  async function remove() {
    const confirmed = await confirm({
      tone: 'danger',
      icon: 'ban',
      eyebrow: 'Votre avis',
      title: 'Retirer votre avis ?',
      message: 'Votre note et votre commentaire disparaîtront de la fiche du projet. '
        + 'Vous pourrez en publier un nouveau à tout moment.',
      confirmLabel: 'Retirer mon avis',
      confirmTone: 'danger',
    })

    if (!confirmed) return
    setSaving(true)
    setError('')
    try {
      await deleteReview(projectId)
      load()
    } catch (err) {
      setError(errorMessage(err, 'Suppression impossible.'))
      setSaving(false)
    }
  }

  if (loading) return null

  return (
    <section style={{ marginTop: '1.5rem' }}>
      <div className="promo-head">
        <h3 style={{ margin: 0 }} className="head-icon"><StarRating value={summary?.average ?? 0} size={17} /> Avis des investisseurs</h3>
        {summary?.count > 0 && (
          <span className="text-muted">
            {summary.average}/5 · {summary.count} avis
          </span>
        )}
      </div>

      {error && <div className="alert alert--error">{error}</div>}

      {canReview && (
        mine && !form ? (
          <div className="card review-form">
            <p className="text-muted" style={{ margin: '0 0 0.6rem' }}>Votre avis</p>
            <StarRating value={mine.rating} size={20} />
            {mine.comment && <p className="review-card__comment">{mine.comment}</p>}
            <div className="review-form__actions">
              <button
                className="btn btn--ghost"
                onClick={() => setForm({ rating: mine.rating, comment: mine.comment ?? '' })}
              >
                Modifier
              </button>
              <button className="btn btn--ghost promo-item__danger" disabled={saving} onClick={remove}>
                Retirer
              </button>
            </div>
          </div>
        ) : (
          <form className="card review-form" onSubmit={submit}>
            <p className="text-muted" style={{ margin: '0 0 0.6rem' }}>
              {mine ? 'Modifier votre avis' : 'Laisser un avis'}
            </p>
            <StarRating
              value={form?.rating ?? 0}
              onChange={(rating) => setForm({ rating, comment: form?.comment ?? '' })}
              size={22}
            />
            <div className="field" style={{ marginTop: '0.8rem', marginBottom: 0 }}>
              <textarea
                rows={3}
                placeholder="Un commentaire, en option"
                value={form?.comment ?? ''}
                onChange={(e) => setForm({ rating: form?.rating ?? 0, comment: e.target.value })}
                maxLength={1000}
              />
            </div>
            <div className="review-form__actions">
              <button className="btn btn--primary" disabled={saving || !form?.rating}>
                {saving ? 'Publication…' : mine ? 'Mettre à jour' : 'Publier mon avis'}
              </button>
              {mine && (
                <button type="button" className="btn btn--ghost" onClick={() => setForm(null)}>
                  Annuler
                </button>
              )}
            </div>
          </form>
        )
      )}

      {reviews.length === 0 ? (
        <p className="text-muted" style={{ marginTop: canReview ? '1rem' : 0 }}>
          Aucun avis pour le moment.
        </p>
      ) : (
        <ul className="review-list" style={{ marginTop: '1rem' }}>
          {reviews.filter((r) => r.investor?.id !== user.id).map((review) => (
            <li key={review.id} className="card review-card">
              <div className="review-card__head">
                <div>
                  <b>{review.investor?.name ?? 'Investisseur'}</b>
                  <div className="review-card__meta">{formatDate(review.created_at)}</div>
                </div>
                <StarRating value={review.rating} size={16} />
              </div>
              {review.comment && <p className="review-card__comment">{review.comment}</p>}
            </li>
          ))}
        </ul>
      )}

      {confirmDialog}
    </section>
  )
}
