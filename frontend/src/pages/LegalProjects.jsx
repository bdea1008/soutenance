import { useCallback, useEffect, useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import StatusBadge from '../components/StatusBadge'
import RiskBadge from '../components/RiskBadge'
import { errorMessage } from '../api/client'
import { fetchProjectStats, fetchProjects } from '../api/admin'
import { formatFCFA, formatPercent } from '../utils/format'

/**
 * Consultation juridique des projets (§5, acteur « Responsable juridique et
 * conformité » du diagramme de cas d'utilisation).
 *
 * Même lecture que la supervision administrative — tous statuts confondus,
 * pour pouvoir vérifier un dossier avant sa validation finale par
 * l'administration — mais sans aucune action : ce rôle consulte, il ne
 * décide pas. D'où une page à part plutôt qu'un partage de `AdminProjects` :
 * pas de boutons « Mettre en ligne »/« Retirer », pas de barre d'onglets
 * `AdminNav` (utilisateurs, finances… hors de son périmètre).
 */

const RISKS = [
  { value: '', label: 'Tous les risques' },
  { value: 'low', label: 'Risque faible' },
  { value: 'medium', label: 'Risque modéré' },
  { value: 'high', label: 'Risque élevé' },
]

function formatDate(value) {
  if (!value) return '—'
  return new Date(value).toLocaleDateString('fr-FR', { day: 'numeric', month: 'short', year: 'numeric' })
}

export default function LegalProjects() {
  const [params, setParams] = useSearchParams()
  const [projects, setProjects] = useState([])
  const [meta, setMeta] = useState(null)
  const [stats, setStats] = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')

  const [search, setSearch] = useState(params.get('q') ?? '')

  const status = params.get('statut') ?? ''
  const risk = params.get('risque') ?? ''
  const query = params.get('q') ?? ''
  const page = Number(params.get('page') ?? 1)

  const load = useCallback(() => {
    setLoading(true)
    Promise.all([
      fetchProjects({
        status: status || undefined,
        risk: risk || undefined,
        search: query || undefined,
        page,
        per_page: 20,
      }),
      fetchProjectStats(),
    ])
      .then(([list, counters]) => {
        setProjects(list.data)
        setMeta(list.meta)
        setStats(counters)
      })
      .catch((err) => setError(errorMessage(err, 'Impossible de charger le catalogue.')))
      .finally(() => setLoading(false))
  }, [status, risk, query, page])

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
      <div className="dash-head">
        <p className="eyebrow">Juridique & conformité</p>
        <h1 style={{ margin: 0 }}>Vérification des projets</h1>
        <p className="text-muted">
          Catalogue complet, tous statuts confondus — lecture seule. La décision de mise en
          ligne reste à l’administration.
        </p>
      </div>

      {stats && (
        <div className="admin-chips">
          <button
            className={`admin-chip ${status === '' ? 'admin-chip--on' : ''}`}
            onClick={() => updateFilter('statut', '')}
          >
            Tous <b>{stats.total}</b>
          </button>
          {stats.by_status.map((row) => (
            <button
              key={row.status}
              className={`admin-chip ${status === row.status ? 'admin-chip--on' : ''}`}
              onClick={() => updateFilter('statut', row.status)}
            >
              {row.label} <b>{row.value}</b>
            </button>
          ))}
        </div>
      )}

      {error && <div className="alert alert--error">{error}</div>}

      <form
        className="admin-filters"
        onSubmit={(e) => { e.preventDefault(); updateFilter('q', search.trim()) }}
      >
        <input
          className="admin-filters__search"
          placeholder="Titre, ville ou région"
          value={search}
          onChange={(e) => setSearch(e.target.value)}
        />
        <select value={risk} onChange={(e) => updateFilter('risque', e.target.value)}>
          {RISKS.map((r) => <option key={r.value} value={r.value}>{r.label}</option>)}
        </select>
        <button className="btn btn--primary" type="submit">Rechercher</button>
      </form>

      {loading ? (
        <div className="spinner" />
      ) : projects.length === 0 ? (
        <div className="card" style={{ padding: '2rem', textAlign: 'center' }}>
          <p className="text-muted" style={{ margin: 0 }}>Aucun projet ne correspond à ces critères.</p>
        </div>
      ) : (
        <div className="card" style={{ marginBottom: '1rem' }}>
          {projects.map((project) => (
            <div key={project.id} className="promo-item">
              <div className="promo-item__main">
                <div className="promo-item__title">
                  <Link to={`/projets/${project.id}`}><b>{project.title}</b></Link>
                  <StatusBadge status={project.status} label={project.status_label} />
                  {project.ai_score && <RiskBadge level={project.ai_score.risk_level} />}
                </div>

                <div className="text-muted" style={{ fontSize: '0.85rem' }}>
                  {project.promoter?.name ?? 'Promoteur inconnu'}
                  {project.promoter && !project.promoter.kyc_verified && ' (KYC non vérifié)'}
                  {' · '}{project.location.city}, {project.location.region}
                  {' · '}déposé le {formatDate(project.created_at)}
                </div>

                <div className="text-muted" style={{ fontSize: '0.85rem' }}>
                  {formatFCFA(project.financials.amount_raised)} collectés sur{' '}
                  {formatFCFA(project.financials.funding_goal)}
                  {' ('}{formatPercent(project.financials.funding_progress)}{')'}
                  {' · '}{project.contributors_count ?? 0} investisseur(s)
                  {project.construction && ` · chantier à ${project.construction.progress_percentage} %`}
                </div>
              </div>
            </div>
          ))}
        </div>
      )}

      {meta && meta.last_page > 1 && (
        <div className="admin-pager">
          <button className="btn btn--ghost" disabled={page <= 1} onClick={() => goToPage(page - 1)}>
            Précédent
          </button>
          <span className="text-muted">
            Page {meta.current_page} sur {meta.last_page} · {meta.total} projet(s)
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
