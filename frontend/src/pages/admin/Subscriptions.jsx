import { useCallback, useEffect, useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import AdminNav from '../../components/AdminNav'
import { errorMessage } from '../../api/client'
import { fetchSubscriptions } from '../../api/admin'
import { formatFCFA } from '../../utils/format'

/**
 * Parc d'abonnements de la plateforme (§16.2.a), vu par l'administration.
 *
 * L'administrateur ne souscrit pas d'abonnement : il regarde ceux des autres.
 * Cet écran liste donc **tous** les contrats, tous promoteurs confondus, là où
 * /promoteur/abonnement ne montre que celui de son titulaire.
 *
 * Les montants encaissés n'y figurent pas : le revenu a son point d'affichage
 * unique dans le suivi financier. Ici on répond à « qui est abonné à quoi,
 * jusqu'à quand », pas à « combien ça rapporte ».
 */

const TIERS = [
  { value: '', label: 'Tous les paliers' },
  { value: 'basic', label: 'Essentiel' },
  { value: 'premium', label: 'Premium' },
  { value: 'enterprise', label: 'Entreprise' },
]

function formatDate(value) {
  if (!value) return '—'
  return new Date(value).toLocaleDateString('fr-FR', { day: 'numeric', month: 'short', year: 'numeric' })
}

/** Quota de projets en ligne attaché au palier (null = illimité). */
function formatQuota(max) {
  return max === null || max === undefined ? 'Illimité' : `${max} projet(s)`
}

export default function AdminSubscriptions() {
  const [params, setParams] = useSearchParams()
  const [rows, setRows] = useState([])
  const [holders, setHolders] = useState({})
  const [counts, setCounts] = useState(null)
  const [meta, setMeta] = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')

  const [search, setSearch] = useState(params.get('q') ?? '')

  const status = params.get('etat') ?? ''
  const tier = params.get('palier') ?? ''
  const promoter = params.get('promoteur') ?? ''
  const query = params.get('q') ?? ''
  const page = Number(params.get('page') ?? 1)

  const load = useCallback(() => {
    setLoading(true)
    fetchSubscriptions({
      status: status || undefined,
      tier: tier || undefined,
      user_id: promoter || undefined,
      search: query || undefined,
      page,
      per_page: 20,
    })
      .then((res) => {
        setRows(res.data)
        setHolders(res.meta_holders ?? {})
        setCounts(res.meta_counts ?? null)
        setMeta(res.meta)
      })
      .catch((err) => setError(errorMessage(err, 'Impossible de charger les abonnements.')))
      .finally(() => setLoading(false))
  }, [status, tier, promoter, query, page])

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

  return (
    <div className="container" style={{ paddingBottom: '3rem' }}>
      <AdminNav
        title="Abonnements"
        subtitle="Tous les contrats promoteur de la plateforme, actifs, échus ou résiliés."
      />

      {counts && (
        <>
          <div className="admin-chips">
            <button
              className={`admin-chip ${status === '' ? 'admin-chip--on' : ''}`}
              onClick={() => updateFilter('etat', '')}
            >
              Tous <b>{counts.total}</b>
            </button>
            {counts.by_status.map((row) => (
              <button
                key={row.status}
                className={`admin-chip ${status === row.status ? 'admin-chip--on' : ''}`}
                onClick={() => updateFilter('etat', row.status)}
              >
                {row.label} <b>{row.value}</b>
              </button>
            ))}
          </div>

          {/* File de travail de l'écran : les contrats à relancer. */}
          {counts.expiring_30d > 0 && (
            <div className="alert alert--info">
              <b>{counts.expiring_30d}</b> abonnement(s) arrivent à échéance sous 30 jours.
              Passé l’échéance, leur titulaire ne peut plus publier de nouveau projet.
            </div>
          )}
        </>
      )}

      {error && <div className="alert alert--error">{error}</div>}

      <form
        className="admin-filters"
        onSubmit={(e) => { e.preventDefault(); updateFilter('q', search.trim()) }}
      >
        <input
          className="admin-filters__search"
          placeholder="Nom, email ou téléphone du promoteur"
          value={search}
          onChange={(e) => setSearch(e.target.value)}
        />
        <select value={tier} onChange={(e) => updateFilter('palier', e.target.value)}>
          {TIERS.map((t) => <option key={t.value} value={t.value}>{t.label}</option>)}
        </select>
        {promoter && (
          <button type="button" className="btn btn--ghost" onClick={() => updateFilter('promoteur', '')}>
            Retirer le filtre promoteur
          </button>
        )}
        <button className="btn btn--primary" type="submit">Rechercher</button>
      </form>

      {loading ? (
        <div className="spinner" />
      ) : rows.length === 0 ? (
        <div className="card" style={{ padding: '2rem', textAlign: 'center' }}>
          <p className="text-muted" style={{ margin: 0 }}>Aucun abonnement ne correspond à ces critères.</p>
        </div>
      ) : (
        <div className="card chart-card">
          <div className="chart-table__wrap">
            <table className="chart-table admin-table">
              <thead>
                <tr>
                  <th>Promoteur</th><th>Palier</th><th>Quota</th><th>Prix mensuel</th>
                  <th>État</th><th>Début</th><th>Échéance</th>
                </tr>
              </thead>
              <tbody>
                {rows.map((row) => (
                  <tr key={row.id}>
                    <td>{holder(holders, row.id)}</td>
                    <td>{row.tier_label}</td>
                    <td>{formatQuota(row.max_active_projects)}</td>
                    <td>{formatFCFA(row.price)}</td>
                    <td>
                      <span className={`badge ${row.is_active ? 'badge--risk-low' : ''}`}>
                        {row.status_label}
                      </span>
                    </td>
                    <td>{formatDate(row.starts_at)}</td>
                    <td>
                      {formatDate(row.ends_at)}
                      {/* L'échéance proche est signalée sur la ligne : c'est
                          là qu'on décide de relancer le promoteur. */}
                      {row.is_active && row.days_remaining <= 30 && (
                        <span className="text-muted" style={{ display: 'block', fontSize: '0.8rem' }}>
                          dans {row.days_remaining} jour(s)
                        </span>
                      )}
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
            Page {meta.current_page} sur {meta.last_page} · {meta.total} abonnement(s)
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
    </div>
  )
}

/**
 * Titulaire d'un contrat. SubscriptionResource est partagée avec l'espace
 * promoteur, où l'utilisateur est implicite : l'API transporte donc les
 * titulaires à côté de la collection (`meta_holders`).
 */
function holder(holders, id) {
  const owner = holders[id]
  if (!owner) return '—'

  return owner.id
    ? <Link to={`/admin/utilisateurs/${owner.id}`}>{owner.name}</Link>
    : owner.name
}
