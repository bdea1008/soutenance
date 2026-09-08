import { useCallback, useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import Icon from '../components/Icon'
import client, { errorMessage } from '../api/client'
import BackLink from '../components/BackLink'

function formatDate(value) {
  return new Date(value).toLocaleDateString('fr-FR', {
    day: 'numeric', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit',
  })
}

/** Boîte de réception complète (§7.7). */
export default function Notifications() {
  const navigate = useNavigate()

  const [items, setItems] = useState([])
  const [meta, setMeta] = useState({ total: 0, unread: 0 })
  const [onlyUnread, setOnlyUnread] = useState(false)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')

  const load = useCallback(() => {
    setLoading(true)
    client
      .get('/me/notifications', { params: { per_page: 50, unread: onlyUnread ? 1 : undefined } })
      .then((res) => {
        setItems(res.data.data)
        setMeta(res.data.meta)
      })
      .catch((err) => setError(errorMessage(err, 'Impossible de charger vos notifications.')))
      .finally(() => setLoading(false))
  }, [onlyUnread])

  useEffect(() => { load() }, [load])

  async function open(notification) {
    if (!notification.read) {
      try {
        await client.post(`/me/notifications/${notification.id}/read`)
      } catch {
        // La navigation prime sur le marquage.
      }
    }
    if (notification.url) navigate(notification.url)
    else load()
  }

  async function markAll() {
    try {
      await client.post('/me/notifications/read-all')
      load()
    } catch (err) {
      setError(errorMessage(err, 'Opération impossible.'))
    }
  }

  async function remove(notification, e) {
    e.stopPropagation()
    try {
      await client.delete(`/me/notifications/${notification.id}`)
      load()
    } catch (err) {
      setError(errorMessage(err, 'Suppression impossible.'))
    }
  }

  return (
    <div className="container">
      <BackLink />
      <div className="dash-head">
        <p className="eyebrow">Mon compte</p>
        <div className="promo-head">
          <h1 style={{ margin: 0 }}>Notifications</h1>
          {meta.unread > 0 && <span className="badge badge--risk-medium">{meta.unread} non lues</span>}
        </div>
        <p className="text-muted">
          Décisions prises sur votre compte et mouvements sur les projets qui vous concernent.
        </p>
      </div>

      {error && <div className="alert alert--error">{error}</div>}

      <div style={{ display: 'flex', gap: '0.5rem', flexWrap: 'wrap', marginBottom: '1.5rem' }}>
        <button
          className={`btn ${onlyUnread ? 'btn--ghost' : 'btn--primary'}`}
          onClick={() => setOnlyUnread(false)}
        >
          Toutes
        </button>
        <button
          className={`btn ${onlyUnread ? 'btn--primary' : 'btn--ghost'}`}
          onClick={() => setOnlyUnread(true)}
        >
          Non lues
        </button>
        {meta.unread > 0 && (
          <button className="btn btn--ghost" onClick={markAll}>Tout marquer comme lu</button>
        )}
      </div>

      {loading ? (
        <div className="spinner" />
      ) : items.length === 0 ? (
        <div className="card" style={{ padding: '2rem', textAlign: 'center' }}>
          <p className="text-muted" style={{ margin: 0 }}>
            {onlyUnread ? 'Aucune notification non lue.' : 'Aucune notification pour le moment.'}
          </p>
        </div>
      ) : (
        <div className="card" style={{ marginBottom: '3rem' }}>
          {items.map((n) => (
            <div
              key={n.id}
              className={`notif${n.read ? '' : ' notif--unread'}`}
              role="button"
              tabIndex={0}
              onClick={() => open(n)}
              onKeyDown={(e) => e.key === 'Enter' && open(n)}
            >
              <Icon name={n.icon} size={20} className="notif__icon" />
              <div className="notif__main">
                <b>{n.title}</b>
                <div className="text-muted" style={{ fontSize: '0.9rem' }}>{n.body}</div>
                <div className="text-muted" style={{ fontSize: '0.8rem', marginTop: '0.3rem' }}>
                  {formatDate(n.created_at)}
                </div>
              </div>
              <button
                className="btn btn--ghost promo-item__danger"
                onClick={(e) => remove(n, e)}
                aria-label="Supprimer la notification"
              >
                ×
              </button>
            </div>
          ))}
        </div>
      )}
    </div>
  )
}
