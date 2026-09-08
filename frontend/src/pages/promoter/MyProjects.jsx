import { useCallback, useEffect, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import client, { errorMessage } from '../../api/client'
import { useAuth } from '../../context/AuthContext'
import { formatDate, formatFCFACompact } from '../../utils/format'
import { attemptPublish } from '../../utils/publish'
import StatusBadge from '../../components/StatusBadge'
import useConfirm from '../../hooks/useConfirm'

/** Statuts depuis lesquels une publication est possible (voir ProjectController::publish). */
const PUBLISHABLE = ['draft', 'pending_review']

// Le suivi de chantier n'a de sens qu'une fois la collecte bouclée (§7.5).
const REPORTABLE = ['funded', 'in_progress', 'completed']

export default function MyProjects() {
  const { user } = useAuth()
  const navigate = useNavigate()
  const [projects, setProjects] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [notice, setNotice] = useState('')
  const [busyId, setBusyId] = useState(null)
  const { confirm, confirmDialog } = useConfirm()

  const load = useCallback(() => {
    setLoading(true)
    client
      .get('/me/projects', { params: { per_page: 50 } })
      .then((res) => setProjects(res.data.data))
      .catch((err) => setError(errorMessage(err, 'Impossible de charger vos projets.')))
      .finally(() => setLoading(false))
  }, [])

  useEffect(() => { load() }, [load])

  async function publish(project) {
    setBusyId(project.id)
    setError('')
    setNotice('')

    const { ok, message, blocker: refusal } = await attemptPublish(project)

    if (ok) {
      setNotice(message)
      load()
    } else if (refusal) {
      // Un refus qu'on sait lever s'explique en fenêtre, avec l'issue — et
      // accepter emmène directement là où on le lève.
      if (await confirm({ ...refusal, arrow: true, cancelLabel: 'Plus tard' })) {
        navigate(refusal.to)
      }
    } else {
      setError(message)
    }

    setBusyId(null)
  }

  async function remove(project) {
    const confirmed = await confirm({
      tone: 'danger',
      icon: 'ban',
      eyebrow: 'Suppression',
      title: 'Supprimer définitivement ce projet ?',
      subtitle: project.title,
      message: 'Le brouillon et tout ce qu’il contient seront perdus, y compris les pièces '
        + 'déjà déposées pour son dossier de financement. Cette action est irréversible.',
      confirmLabel: 'Supprimer le projet',
      confirmTone: 'danger',
    })

    if (!confirmed) return
    setBusyId(project.id)
    setError('')
    setNotice('')
    try {
      const res = await client.delete(`/projects/${project.id}`)
      setNotice(res.data.message)
      load()
    } catch (err) {
      setError(errorMessage(err, 'Suppression impossible.'))
    } finally {
      setBusyId(null)
    }
  }

  return (
    <div className="container">
      <div className="dash-head">
        <p className="eyebrow">Espace promoteur</p>
        <div className="promo-head">
          <h1 style={{ margin: 0 }}>Mes projets</h1>
          <Link to="/promoteur/projets/nouveau" className="btn btn--primary">+ Nouveau projet</Link>
        </div>
      </div>

      {/* Prérequis §7.2, dans l'ordre où le serveur les vérifie à la
          publication : dossier d'opérateur, abonnement, puis dossier de
          l'opération (celui-ci est propre à chaque projet, il est rappelé sur
          la ligne du projet concerné). Créer un brouillon n'en exige aucun. */}
      {!user.kyc_verified && (
        <div className="alert alert--info">
          Votre dossier {user.promoter_type_label?.toLowerCase() ?? 'promoteur'} est{' '}
          <b>{user.kyc_status}</b>. Vous pouvez préparer vos brouillons, mais il doit être validé
          avant toute publication. <Link to="/verification">Déposer mes pièces →</Link>
        </div>
      )}
      {user.kyc_verified && !user.has_active_subscription && (
        <div className="alert alert--info">
          Vous pouvez préparer vos brouillons, mais un <b>abonnement promoteur actif</b> est requis pour les publier.
          {' '}<Link to="/promoteur/abonnement">Choisir un abonnement →</Link>
        </div>
      )}

      {error && <div className="alert alert--error">{error}</div>}
      {notice && <div className="alert alert--success">{notice}</div>}

      {confirmDialog}

      <section className="section" style={{ paddingTop: '1rem' }}>
        {loading ? (
          <div className="spinner" />
        ) : projects.length === 0 ? (
          <div className="card" style={{ padding: '2rem', textAlign: 'center' }}>
            <p className="text-muted">Vous n’avez encore aucun projet.</p>
            <Link to="/promoteur/projets/nouveau" className="btn btn--primary">Créer mon premier projet</Link>
          </div>
        ) : (
          <div className="card">
            {projects.map((p) => (
              <div key={p.id} className="promo-item">
                <div className="promo-item__main">
                  <div className="promo-item__title">
                    <b>{p.title}</b>
                    <StatusBadge status={p.status} label={p.status_label} />
                  </div>
                  <div className="text-muted" style={{ fontSize: '0.85rem' }}>
                    {p.location.city || 'Localisation non précisée'}
                    {' · '}objectif {formatFCFACompact(p.financials.funding_goal)}
                    {' · '}{p.contributors_count ?? 0} contribution{(p.contributors_count ?? 0) > 1 ? 's' : ''}
                    {p.published_at && <>{' · '}publié le {formatDate(p.published_at)}</>}
                  </div>
                  <div className="progress" style={{ marginTop: '0.5rem', maxWidth: 320 }}>
                    <span style={{ width: `${Math.min(100, p.financials.funding_progress)}%` }} />
                  </div>
                  <div className="text-muted" style={{ fontSize: '0.8rem', marginTop: '0.25rem' }}>
                    {formatFCFACompact(p.financials.amount_raised)} collectés ({p.financials.funding_progress}%)
                  </div>
                  {/* Dossier de l'opération : ce qui bloque encore la mise en
                      financement de ce projet-là, chiffré sur sa propre ligne. */}
                  {p.dossier && PUBLISHABLE.includes(p.status) && (
                    <div className="text-muted" style={{ fontSize: '0.8rem', marginTop: '0.35rem' }}>
                      Dossier de financement : {p.dossier.satisfied}/{p.dossier.required} pièces validées
                      {!p.dossier.complete && (
                        <> · <Link to={`/promoteur/projets/${p.id}/dossier`}>compléter →</Link></>
                      )}
                    </div>
                  )}
                </div>

                <div className="promo-item__actions">
                  <Link to={`/projets/${p.id}`} className="btn btn--ghost">Voir</Link>
                  <Link to={`/promoteur/projets/${p.id}/modifier`} className="btn btn--ghost">Modifier</Link>
                  <Link to={`/promoteur/projets/${p.id}/dossier`} className="btn btn--ghost">Dossier</Link>
                  {REPORTABLE.includes(p.status) && (
                    <Link to={`/promoteur/projets/${p.id}/rapports`} className="btn btn--ghost">
                      Chantier
                    </Link>
                  )}
                  {PUBLISHABLE.includes(p.status) && (
                    <button
                      className="btn btn--accent"
                      disabled={busyId === p.id}
                      onClick={() => publish(p)}
                    >
                      {busyId === p.id ? '…' : 'Publier'}
                    </button>
                  )}
                  <button
                    className="btn btn--ghost promo-item__danger"
                    disabled={busyId === p.id}
                    onClick={() => remove(p)}
                  >
                    Supprimer
                  </button>
                </div>
              </div>
            ))}
          </div>
        )}
      </section>
    </div>
  )
}
