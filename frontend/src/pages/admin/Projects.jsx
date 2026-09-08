import { useCallback, useEffect, useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import AdminNav from '../../components/AdminNav'
import StatusBadge from '../../components/StatusBadge'
import RiskBadge from '../../components/RiskBadge'
import { errorMessage } from '../../api/client'
import { fetchProjectStats, fetchProjects, moderateProject } from '../../api/admin'
import { formatFCFA, formatPercent } from '../../utils/format'
import useConfirm from '../../hooks/useConfirm'

/**
 * Supervision du catalogue (§5).
 *
 * Le retrait d'un projet n'est jamais une suppression : un projet ayant reçu
 * des contributions doit rester dans l'historique des investisseurs. L'écran
 * ne propose donc que « retirer » et « rétablir », jamais « supprimer ».
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

export default function AdminProjects() {
  const { confirm, confirmDialog } = useConfirm()
  const [params, setParams] = useSearchParams()
  const [projects, setProjects] = useState([])
  const [meta, setMeta] = useState(null)
  const [stats, setStats] = useState(null)
  const [loading, setLoading] = useState(true)
  const [busyId, setBusyId] = useState(null)
  const [error, setError] = useState('')
  const [notice, setNotice] = useState('')

  const [search, setSearch] = useState(params.get('q') ?? '')

  const status = params.get('statut') ?? ''
  const risk = params.get('risque') ?? ''
  const promoter = params.get('promoteur') ?? ''
  const query = params.get('q') ?? ''
  const page = Number(params.get('page') ?? 1)

  const load = useCallback(() => {
    setLoading(true)
    Promise.all([
      fetchProjects({
        status: status || undefined,
        risk: risk || undefined,
        promoter_id: promoter || undefined,
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
  }, [status, risk, promoter, query, page])

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

  async function moderate(project, decision) {
    let reason

    if (decision === 'cancel') {
      reason = await confirm({
        tone: 'danger',
        icon: 'ban',
        eyebrow: 'Modération',
        title: 'Retirer ce projet du catalogue ?',
        subtitle: project.title,
        message: 'Il disparaîtra du catalogue public et n’acceptera plus d’investissement. '
          + 'Il n’est jamais supprimé : les contributions déjà reçues restent dans '
          + 'l’historique de leurs investisseurs.',
        prompt: {
          label: 'Motif du retrait',
          placeholder: 'Dossier incomplet, informations trompeuses…',
          hint: 'Le promoteur recevra ce motif par notification.',
          required: true,
          requiredMessage: 'Un motif est requis pour retirer un projet.',
        },
        confirmLabel: 'Retirer le projet',
        confirmTone: 'danger',
      })

      if (reason === null) return
    } else if (!await confirm(
      decision === 'approve'
        ? {
          tone: 'info',
          icon: 'check',
          eyebrow: 'Modération',
          title: 'Mettre ce projet en ligne ?',
          subtitle: project.title,
          message: 'Il apparaîtra au catalogue public et sera ouvert au co-investissement.',
          confirmLabel: 'Mettre en ligne',
        }
        : {
          tone: 'info',
          icon: 'undo',
          eyebrow: 'Modération',
          title: 'Rétablir ce projet ?',
          subtitle: project.title,
          message: 'Il repart en brouillon chez son promoteur — ou reste publié s’il a déjà collecté, '
            + 'auquel cas il ne peut plus redevenir librement modifiable.',
          confirmLabel: 'Rétablir',
        }
    )) {
      return
    }

    setBusyId(project.id)
    setError('')
    setNotice('')
    try {
      const res = await moderateProject(project.id, decision, reason)
      setNotice(`${res.message} — « ${project.title} ».`)
      load()
    } catch (err) {
      setError(errorMessage(err, 'Décision impossible.'))
    } finally {
      setBusyId(null)
    }
  }

  return (
    <div className="container">
      <AdminNav
        title="Projets"
        subtitle="Catalogue complet, brouillons et projets retirés compris."
      />

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
      {notice && <div className="alert alert--success">{notice}</div>}

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
        {promoter && (
          <button type="button" className="btn btn--ghost" onClick={() => updateFilter('promoteur', '')}>
            Retirer le filtre promoteur
          </button>
        )}
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

              <div className="promo-item__actions">
                {['draft', 'pending_review'].includes(project.status) && (
                  <button
                    className="btn btn--primary"
                    disabled={busyId === project.id}
                    onClick={() => moderate(project, 'approve')}
                  >
                    Mettre en ligne
                  </button>
                )}

                {project.status === 'cancelled' ? (
                  <button
                    className="btn btn--ghost"
                    disabled={busyId === project.id}
                    onClick={() => moderate(project, 'restore')}
                  >
                    Rétablir
                  </button>
                ) : project.status !== 'completed' && (
                  <button
                    className="btn btn--ghost promo-item__danger"
                    disabled={busyId === project.id}
                    onClick={() => moderate(project, 'cancel')}
                  >
                    Retirer
                  </button>
                )}
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

      {confirmDialog}
    </div>
  )
}
