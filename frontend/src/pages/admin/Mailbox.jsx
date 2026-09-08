import { useCallback, useEffect, useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import AdminNav from '../../components/AdminNav'
import Icon from '../../components/Icon'
import { errorMessage } from '../../api/client'
import { clearEmails, fetchEmails } from '../../api/admin'
import useConfirm from '../../hooks/useConfirm'

/**
 * Boîte d'envoi simulée, vue par l'administration.
 *
 * Pendant que l'intégration d'un serveur de messagerie n'est pas faite, aucun
 * email ne quitte réellement la plateforme : chaque message est composé,
 * rendu, puis déposé ici. C'est le pendant, pour les emails, du journal des
 * paiements pour les paiements simulés — on montre ce qui *serait* parti.
 *
 * L'écran ne rend pas le HTML du message : il renvoie vers une page servie
 * par le backend, qui l'affiche dans un cadre isolé. Injecter du HTML étranger
 * dans l'application n'apporterait rien et lui ferait perdre son cloisonnement.
 */

function formatDate(value) {
  if (!value) return '—'
  return new Date(value).toLocaleDateString('fr-FR', {
    day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit',
  })
}

export default function AdminMailbox() {
  const { confirm, confirmDialog } = useConfirm()
  const [params, setParams] = useSearchParams()
  const [rows, setRows] = useState([])
  const [counts, setCounts] = useState(null)
  const [meta, setMeta] = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [notice, setNotice] = useState('')

  const [search, setSearch] = useState(params.get('q') ?? '')

  const context = params.get('type') ?? ''
  const query = params.get('q') ?? ''
  const page = Number(params.get('page') ?? 1)

  const load = useCallback(() => {
    setLoading(true)
    fetchEmails({ context: context || undefined, q: query || undefined, page, per_page: 20 })
      .then((res) => {
        setRows(res.data)
        setCounts(res.meta_counts ?? null)
        setMeta(res.meta)
      })
      .catch((err) => setError(errorMessage(err, 'Impossible de charger la boîte d’envoi.')))
      .finally(() => setLoading(false))
  }, [context, query, page])

  useEffect(() => { load() }, [load])

  function updateFilter(key, value) {
    const next = new URLSearchParams(params)
    if (value) next.set(key, value)
    else next.delete(key)
    next.delete('page')
    setParams(next)
  }

  function goToPage(target) {
    const next = new URLSearchParams(params)
    next.set('page', String(target))
    setParams(next)
  }

  async function empty() {
    const confirmed = await confirm({
      tone: 'danger',
      icon: 'ban',
      eyebrow: 'Boîte d’envoi',
      title: 'Vider la boîte d’envoi simulée ?',
      message: 'Tous les messages enregistrés seront supprimés, y compris les liens de '
        + 'réinitialisation encore valides. Ils ne sont pas récupérables.',
      confirmLabel: 'Vider la boîte',
      confirmTone: 'danger',
    })

    if (!confirmed) return

    setError('')
    try {
      const res = await clearEmails()
      setNotice(res.message)
      // La page courante peut ne plus exister après une purge.
      const next = new URLSearchParams(params)
      next.delete('page')
      setParams(next)
      load()
    } catch (err) {
      setError(errorMessage(err, 'Suppression impossible.'))
    }
  }

  return (
    <div className="container" style={{ paddingBottom: '3rem' }}>
      <AdminNav
        title="Messages envoyés"
        subtitle="Tout ce que la plateforme a adressé à ses utilisateurs par email."
        action={
          rows.length > 0 && (
            <button className="btn btn--ghost" onClick={empty}>Vider la boîte</button>
          )
        }
      />

      {/* Le statut de la simulation est la première chose à dire : sans lui,
          une boîte vide se lit comme une panne d'envoi. */}
      {counts && (
        counts.simulation_active ? (
          <div className="alert alert--info">
            <b>Envoi simulé.</b> Aucun message ne quitte réellement la plateforme : chacun est
            composé et rendu normalement, puis déposé ici au lieu d’être remis à un serveur de
            messagerie. Le destinataire ne reçoit donc rien — c’est cette page qui en tient lieu.
          </div>
        ) : (
          <div className="alert alert--warning">
            <b>Envoi réel activé.</b> Les messages partent désormais vers les boîtes des
            destinataires et ne sont plus déposés ici. Cette page ne montre que l’historique
            de la période simulée.
          </div>
        )
      )}

      {error && <div className="alert alert--error">{error}</div>}
      {notice && <div className="alert alert--success">{notice}</div>}

      {counts && counts.total > 0 && (
        <div className="admin-chips">
          <button
            className={`admin-chip ${context === '' ? 'admin-chip--on' : ''}`}
            onClick={() => updateFilter('type', '')}
          >
            Tous <b>{counts.total}</b>
          </button>
          {counts.by_context.map((row) => (
            <button
              key={row.context ?? 'autre'}
              className={`admin-chip ${context === row.context ? 'admin-chip--on' : ''}`}
              onClick={() => updateFilter('type', row.context)}
            >
              {row.label} <b>{row.value}</b>
            </button>
          ))}
        </div>
      )}

      <form
        className="admin-filters"
        onSubmit={(e) => { e.preventDefault(); updateFilter('q', search.trim()) }}
      >
        <input
          className="admin-filters__search"
          placeholder="Destinataire ou objet du message"
          value={search}
          onChange={(e) => setSearch(e.target.value)}
        />
        <button className="btn btn--primary" type="submit">Rechercher</button>
      </form>

      {loading ? (
        <div className="spinner" />
      ) : rows.length === 0 ? (
        <div className="card" style={{ padding: '2rem', textAlign: 'center' }}>
          <p className="text-muted" style={{ margin: 0 }}>
            {query || context
              ? 'Aucun message ne correspond à ces critères.'
              : 'Aucun message envoyé pour l’instant.'}
          </p>
        </div>
      ) : (
        <div className="card chart-card">
          <div className="chart-table__wrap">
            <table className="chart-table admin-table">
              <thead>
                <tr>
                  <th>Destinataire</th><th>Objet</th><th>Nature</th><th>Envoyé le</th><th>Message</th>
                </tr>
              </thead>
              <tbody>
                {rows.map((row) => (
                  <tr key={row.id}>
                    <td>
                      {row.recipient
                        ? (
                          <>
                            <Link to={`/admin/utilisateurs/${row.recipient.id}`}>
                              {row.recipient.name}
                            </Link>
                            <span className="text-muted" style={{ display: 'block', fontSize: '0.8rem' }}>
                              {row.to.email}
                            </span>
                          </>
                        )
                        // Adresse hors plateforme : le compte a pu être
                        // supprimé, ou le message viser une adresse inconnue.
                        : <span>{row.to.email}</span>}
                    </td>
                    <td>{row.subject}</td>
                    <td><span className="badge badge--info">{row.context_label}</span></td>
                    <td>{formatDate(row.sent_at)}</td>
                    <td>
                      <a
                        className="btn btn--ghost btn--sm"
                        href={row.preview_url}
                        target="_blank"
                        rel="noreferrer"
                      >
                        <Icon name="mail" size={16} /> Ouvrir
                      </a>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {meta && meta.last_page > 1 && (
        <div className="admin-pager">
          <button className="btn btn--ghost" disabled={page <= 1} onClick={() => goToPage(page - 1)}>
            Précédent
          </button>
          <span className="text-muted">
            Page {meta.current_page} sur {meta.last_page} · {meta.total} message(s)
          </span>
          <button
            className="btn btn--ghost"
            disabled={page >= meta.last_page}
            onClick={() => goToPage(page + 1)}
          >
            Suivant
          </button>
        </div>
      )}

      {confirmDialog}
    </div>
  )
}
