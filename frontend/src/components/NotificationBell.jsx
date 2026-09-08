import { useCallback, useEffect, useRef, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import Icon from './Icon'
import client from '../api/client'

/** Rafraîchissement du compteur, en millisecondes. */
const POLL_MS = 60_000

function relativeDate(value) {
  const diff = (Date.now() - new Date(value).getTime()) / 1000
  if (diff < 60) return "à l'instant"
  if (diff < 3600) return `il y a ${Math.floor(diff / 60)} min`
  if (diff < 86_400) return `il y a ${Math.floor(diff / 3600)} h`
  if (diff < 604_800) return `il y a ${Math.floor(diff / 86_400)} j`
  return new Date(value).toLocaleDateString('fr-FR', { day: 'numeric', month: 'short' })
}

/**
 * Cloche de notifications (§7.7) : pastille du nombre de non-lues et aperçu
 * des plus récentes. Le compteur est rafraîchi périodiquement — le MVP n'a pas
 * de canal temps réel, un sondage discret suffit à cette échelle.
 */
export default function NotificationBell() {
  const navigate = useNavigate()
  const panel = useRef(null)

  const [unread, setUnread] = useState(0)
  const [items, setItems] = useState([])
  const [open, setOpen] = useState(false)
  const [loading, setLoading] = useState(false)

  const refreshCount = useCallback(() => {
    client
      .get('/me/notifications/unread')
      .then((res) => setUnread(res.data.unread))
      .catch(() => {})
  }, [])

  useEffect(() => {
    refreshCount()
    const timer = setInterval(refreshCount, POLL_MS)
    return () => clearInterval(timer)
  }, [refreshCount])

  // Fermeture au clic en dehors du panneau.
  useEffect(() => {
    if (!open) return undefined

    function onClickOutside(e) {
      if (panel.current && !panel.current.contains(e.target)) setOpen(false)
    }
    document.addEventListener('mousedown', onClickOutside)
    return () => document.removeEventListener('mousedown', onClickOutside)
  }, [open])

  function toggle() {
    const next = !open
    setOpen(next)
    if (!next) return

    setLoading(true)
    client
      .get('/me/notifications', { params: { per_page: 6 } })
      .then((res) => {
        setItems(res.data.data)
        setUnread(res.data.meta.unread)
      })
      .catch(() => setItems([]))
      .finally(() => setLoading(false))
  }

  async function openNotification(notification) {
    setOpen(false)

    if (!notification.read) {
      try {
        const res = await client.post(`/me/notifications/${notification.id}/read`)
        setUnread(res.data.unread)
      } catch {
        // Un échec de marquage ne doit pas empêcher la navigation.
      }
    }

    navigate(notification.url || '/notifications')
  }

  return (
    <div className="bell" ref={panel}>
      <button
        className="btn btn--ghost bell__button"
        onClick={toggle}
        aria-label={`Notifications${unread > 0 ? ` (${unread} non lues)` : ''}`}
        aria-expanded={open}
      >
        <Icon name="bell" size={20} />
        {unread > 0 && <span className="bell__badge">{unread > 9 ? '9+' : unread}</span>}
      </button>

      {open && (
        <div className="bell__panel card">
          <div className="bell__head">
            <b>Notifications</b>
            <Link to="/notifications" onClick={() => setOpen(false)}>Tout voir</Link>
          </div>

          {loading ? (
            <div className="spinner" />
          ) : items.length === 0 ? (
            <p className="text-muted bell__empty">Aucune notification pour le moment.</p>
          ) : (
            <ul className="bell__list">
              {items.map((n) => (
                <li key={n.id}>
                  <button
                    className={`bell__item${n.read ? '' : ' bell__item--unread'}`}
                    onClick={() => openNotification(n)}
                  >
                    <Icon name={n.icon} size={18} className="bell__icon" />
                    <span className="bell__text">
                      <b>{n.title}</b>
                      <span className="bell__body">{n.body}</span>
                      <span className="bell__date">{relativeDate(n.created_at)}</span>
                    </span>
                  </button>
                </li>
              ))}
            </ul>
          )}
        </div>
      )}
    </div>
  )
}
