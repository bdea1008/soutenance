import { useCallback, useEffect, useState } from 'react'
import { Link, useLocation, useNavigate, useParams } from 'react-router-dom'
import AdminNav from '../../components/AdminNav'
import StatusBadge from '../../components/StatusBadge'
import { errorMessage } from '../../api/client'
import { downloadDocument } from '../../api/documents'
import { deleteUser, fetchUser, setUserRole, setUserStatus } from '../../api/admin'
import { formatFCFA, formatPercent } from '../../utils/format'
import { useAuth } from '../../context/AuthContext'
import BackLink from '../../components/BackLink'
import useConfirm from '../../hooks/useConfirm'

/**
 * Fiche complète d'un compte : tout ce sur quoi un gestionnaire s'appuie pour
 * décider d'une suspension ou d'un changement de rôle, sur un seul écran.
 */

const ROLES = [
  { value: 'investor', label: 'Investisseur' },
  { value: 'promoter', label: 'Promoteur' },
  { value: 'admin', label: 'Administrateur' },
  { value: 'legal', label: 'Juridique & conformité' },
]

const DOC_TONE = {
  approved: 'badge--risk-low',
  pending: 'badge--risk-medium',
  rejected: 'badge--risk-high',
  // Pièce validée mais hors délai : elle ne compte plus pour le dossier.
  expired: 'badge--risk-high',
}

function formatDate(value) {
  if (!value) return '—'
  return new Date(value).toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' })
}

export default function AdminUserDetail() {
  const { confirm, confirmDialog } = useConfirm()
  const { id } = useParams()
  const { user: me } = useAuth()
  const navigate = useNavigate()
  const location = useLocation()

  const [data, setData] = useState(null)
  const [loading, setLoading] = useState(true)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')
  // Message de confirmation transmis par l'écran de création du compte.
  const [notice, setNotice] = useState(location.state?.notice ?? '')

  const load = useCallback(() => {
    setLoading(true)
    fetchUser(id)
      .then(setData)
      .catch((err) => setError(errorMessage(err, 'Compte introuvable.')))
      .finally(() => setLoading(false))
  }, [id])

  useEffect(() => { load() }, [load])

  const account = data?.user
  // Un administrateur ne peut agir ni sur son propre compte ni sur son propre
  // rôle : l'API le refuse, l'écran ne propose donc pas l'action.
  const isSelf = account && me && account.id === me.id

  async function toggleStatus() {
    const suspending = account.is_active
    let reason

    if (suspending) {
      reason = await confirm({
        tone: 'danger',
        icon: 'ban',
        eyebrow: 'Compte',
        title: 'Suspendre ce compte ?',
        subtitle: `${account.name} · ${account.email}`,
        message: 'Le titulaire sera déconnecté et ne pourra plus accéder à la plateforme, '
          + 'même avec une session déjà ouverte.',
        prompt: {
          label: 'Motif de la suspension',
          placeholder: 'Pièces d’identité non conformes, activité suspecte…',
          hint: 'Le titulaire recevra ce motif par notification : c’est la seule explication qu’il aura de sa suspension.',
          required: true,
          requiredMessage: 'Un motif est requis pour désactiver un compte.',
        },
        confirmLabel: 'Suspendre le compte',
        confirmTone: 'danger',
      })

      if (reason === null) return
    } else if (!await confirm({
      tone: 'info',
      icon: 'undo',
      eyebrow: 'Compte',
      title: 'Réactiver ce compte ?',
      subtitle: `${account.name} · ${account.email}`,
      message: 'Le titulaire pourra de nouveau se connecter et retrouvera l’ensemble de ses droits.',
      confirmLabel: 'Réactiver',
    })) {
      return
    }

    await run(() => setUserStatus(account.id, !suspending, reason))
  }

  async function changeRole(role) {
    if (role === account.role) return
    const confirmed = await confirm({
      tone: 'warning',
      icon: 'users',
      eyebrow: 'Rôle',
      title: `Changer le rôle en « ${ROLES.find((r) => r.value === role).label} » ?`,
      subtitle: account.name,
      message: 'Sa vérification KYC sera recalculée : les pièces exigées ne sont pas les mêmes '
        + 'd’un rôle à l’autre. Un compte vérifié peut donc repasser « non vérifié ».',
      confirmLabel: 'Changer le rôle',
    })

    if (!confirmed) return

    await run(() => setUserRole(account.id, role))
  }

  /**
   * Suppression douce : le compte disparaît de l'annuaire et ne peut plus se
   * connecter, mais ses projets, contributions et paiements restent intacts —
   * comme un projet retiré, jamais supprimé de l'historique.
   */
  async function removeAccount() {
    const confirmed = await confirm({
      tone: 'danger',
      icon: 'ban',
      eyebrow: 'Suppression',
      title: 'Supprimer ce compte ?',
      subtitle: `${account.name} · ${account.email}`,
      message: 'Le titulaire ne pourra plus se connecter et disparaîtra de l’annuaire. '
        + 'Son historique — projets, investissements, paiements — reste intact : la suppression '
        + 'est douce, jamais réelle en base.',
      confirmLabel: 'Supprimer le compte',
      confirmTone: 'danger',
    })

    if (!confirmed) return

    setBusy(true)
    setError('')
    try {
      const res = await deleteUser(account.id)
      navigate('/admin/utilisateurs', { replace: true, state: { notice: res.message } })
    } catch (err) {
      setError(errorMessage(err, 'Suppression impossible.'))
      setBusy(false)
    }
  }

  /** Enveloppe commune des actions : état occupé, message, rechargement. */
  async function run(action) {
    setBusy(true)
    setError('')
    setNotice('')
    try {
      const res = await action()
      setNotice(res.message)
      load()
    } catch (err) {
      setError(errorMessage(err, 'Action impossible.'))
    } finally {
      setBusy(false)
    }
  }

  async function download(doc) {
    try {
      await downloadDocument(doc)
    } catch {
      setError('Téléchargement impossible.')
    }
  }

  if (loading) return <div className="container"><div className="spinner" /></div>

  if (!account) {
    return (
      <div className="container">
        <AdminNav title="Compte introuvable" />
        {error && <div className="alert alert--error">{error}</div>}
        <Link to="/admin/utilisateurs" className="btn btn--ghost">Retour à l’annuaire</Link>
      </div>
    )
  }

  return (
    <div className="container" style={{ paddingBottom: '3rem' }}>
      <BackLink to="/admin/utilisateurs" label="Annuaire des comptes" />
      {/* Le sous-type de promoteur commande les pièces exigées : il doit être
          lisible d'emblée, sinon la checklist paraît arbitraire. */}
      <AdminNav
        title={account.name}
        subtitle={[
          account.promoter_type_label
            ? `${account.role_label} · ${account.promoter_type_label}`
            : account.role_label,
          account.company?.name,
          `inscrit le ${formatDate(account.created_at)}`,
        ].filter(Boolean).join(' · ')}
      />

      {error && <div className="alert alert--error">{error}</div>}
      {notice && <div className="alert alert--success">{notice}</div>}

      <div className="detail-grid">
        <div>
          {/* --- Identité et dossier de vérification --- */}
          <section className="card chart-card">
            <div className="chart-card__head">
              <div>
                <h2 className="chart-card__title">Dossier de vérification</h2>
                <p className="chart-card__subtitle">
                  Le statut KYC découle des pièces : il ne se force pas à la main.
                </p>
              </div>
              <span className={`badge ${account.kyc_status === 'verified' ? 'badge--risk-low' : ''}`}>
                {account.kyc_status_label}
              </span>
            </div>

            {data.kyc_checklist.length === 0 ? (
              <p className="text-muted" style={{ margin: 0 }}>
                Aucune pièce n’est exigée pour ce rôle.
              </p>
            ) : (
              <div className="kyc-checklist">
                {data.kyc_checklist.map((item) => (
                  <div key={item.type} className="kyc-check">
                    <span className={`kyc-check__mark ${item.status === 'approved' ? 'kyc-check__mark--done' : ''}`}>
                      {item.status === 'approved' ? '✓' : '•'}
                    </span>
                    <span className="kyc-check__label">
                      {item.label}
                      {!item.required && <span className="dossier-check__optional">facultatif</span>}
                      {item.expires_at && (
                        <small className="dossier-check__hint">
                          Valable jusqu’au {formatDate(item.expires_at)}.
                        </small>
                      )}
                    </span>
                    <span className={`badge ${DOC_TONE[item.status] ?? ''}`}>{item.status_label}</span>
                  </div>
                ))}
              </div>
            )}

            {data.documents.length > 0 && (
              <ul className="admin-feed" style={{ marginTop: '1rem' }}>
                {data.documents.map((doc) => (
                  <li key={doc.id} className="admin-feed__item">
                    <div className="admin-feed__main">
                      <b>{doc.type_label}</b>
                      <div className="admin-feed__meta">
                        {doc.original_name} · déposée le {formatDate(doc.created_at)}
                        {doc.review_note && ` · motif : ${doc.review_note}`}
                      </div>
                    </div>
                    <div className="admin-feed__side">
                      <span className={`badge ${DOC_TONE[doc.status] ?? ''}`}>{doc.status_label}</span>
                      <button
                        className="btn btn--ghost"
                        style={{ padding: '0.3rem 0.7rem', fontSize: '0.82rem' }}
                        onClick={() => download(doc)}
                      >
                        Consulter
                      </button>
                    </div>
                  </li>
                ))}
              </ul>
            )}

            {data.documents.some((d) => d.status === 'pending') && (
              <p className="chart-card__footer">
                <Link to="/admin/documents">Statuer sur les pièces en attente →</Link>
              </p>
            )}
          </section>

          {/* --- Projets portés (promoteur) --- */}
          {data.projects.length > 0 && (
            <section className="card chart-card">
              <div className="chart-card__head">
                <div>
                  <h2 className="chart-card__title">Projets portés</h2>
                  <p className="chart-card__subtitle">{data.projects.length} projet(s), tous statuts</p>
                </div>
                <Link to={`/admin/projets?promoteur=${account.id}`} className="chart-card__toggle">
                  Superviser
                </Link>
              </div>

              <div className="chart-table__wrap">
                <table className="chart-table admin-table">
                  <thead>
                    <tr>
                      <th>Projet</th><th>Statut</th><th>Objectif</th><th>Collecté</th><th>Avancement</th>
                    </tr>
                  </thead>
                  <tbody>
                    {data.projects.map((project) => (
                      <tr key={project.id}>
                        <td><Link to={`/projets/${project.id}`}>{project.title}</Link></td>
                        <td><StatusBadge status={project.status} label={project.status_label} /></td>
                        <td>{formatFCFA(project.financials.funding_goal)}</td>
                        <td>{formatFCFA(project.financials.amount_raised)}</td>
                        <td>{formatPercent(project.financials.funding_progress)}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </section>
          )}

          {/* --- Investissements (investisseur) --- */}
          {data.contributions.length > 0 && (
            <section className="card chart-card">
              <div className="chart-card__head">
                <div>
                  <h2 className="chart-card__title">Investissements</h2>
                  <p className="chart-card__subtitle">
                    {data.contributions.length} contribution(s) — simulées en MVP
                  </p>
                </div>
              </div>

              <div className="chart-table__wrap">
                <table className="chart-table admin-table">
                  <thead>
                    <tr>
                      <th>Projet</th><th>Montant</th><th>Part</th><th>Rendement estimé</th>
                      <th>Paiement</th><th>État</th><th>Date</th>
                    </tr>
                  </thead>
                  <tbody>
                    {data.contributions.map((contribution) => (
                      <tr key={contribution.id}>
                        <td>
                          <Link to={`/projets/${contribution.project_id}`}>
                            {contribution.project?.title ?? `Projet #${contribution.project_id}`}
                          </Link>
                        </td>
                        <td>{formatFCFA(contribution.amount)}</td>
                        <td>{formatPercent(contribution.share_percentage)}</td>
                        <td>{formatFCFA(contribution.estimated_return)}</td>
                        <td>
                          {contribution.payment
                            ? <span title={contribution.payment.reference}>{contribution.payment.provider_label}</span>
                            : '—'}
                        </td>
                        <td>{contribution.status_label}</td>
                        <td>{formatDate(contribution.confirmed_at ?? contribution.created_at)}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </section>
          )}

          {/* --- Abonnements (promoteur) --- */}
          {data.subscriptions.length > 0 && (
            <section className="card chart-card">
              <div className="chart-card__head">
                <div>
                  <h2 className="chart-card__title">Abonnements</h2>
                  <p className="chart-card__subtitle">Prérequis de la publication de projets</p>
                </div>
                <Link to={`/admin/abonnements?promoteur=${account.id}`} className="chart-card__toggle">
                  Voir dans le parc →
                </Link>
              </div>

              <div className="chart-table__wrap">
                <table className="chart-table admin-table">
                  <thead>
                    <tr><th>Palier</th><th>Prix</th><th>État</th><th>Début</th><th>Échéance</th></tr>
                  </thead>
                  <tbody>
                    {data.subscriptions.map((subscription) => (
                      <tr key={subscription.id}>
                        <td>{subscription.tier_label}</td>
                        <td>{formatFCFA(subscription.price)}</td>
                        <td>
                          <span className={`badge ${subscription.is_active ? 'badge--risk-low' : ''}`}>
                            {subscription.status_label}
                          </span>
                        </td>
                        <td>{formatDate(subscription.starts_at)}</td>
                        <td>{formatDate(subscription.ends_at)}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </section>
          )}
        </div>

        {/* --- Colonne d'action --- */}
        <aside>
          <section className="card invest-box">
            <h2 style={{ marginTop: 0, fontSize: '1.05rem' }}>Fiche du compte</h2>

            <div className="data-row"><span>Email</span><b>{account.email}</b></div>
            <div className="data-row"><span>Téléphone</span><b>{account.phone || '—'}</b></div>
            <div className="data-row">
              <span>Localisation</span>
              <b>{[account.city, account.country].filter(Boolean).join(', ') || '—'}</b>
            </div>
            <div className="data-row">
              <span>État</span>
              <b className={account.is_active ? '' : 'promo-item__danger'}>
                {account.is_active ? 'Actif' : 'Désactivé'}
              </b>
            </div>
            {account.role === 'promoter' && (
              <div className="data-row">
                <span>Collecté</span><b>{formatFCFA(account.raised_total ?? 0)}</b>
              </div>
            )}
            {account.role === 'investor' && (
              <div className="data-row">
                <span>Investi</span><b>{formatFCFA(account.invested_total ?? 0)}</b>
              </div>
            )}

            <div style={{ marginTop: '1.2rem', paddingTop: '1rem', borderTop: '1px solid var(--color-border)' }}>
              {isSelf ? (
                <p className="text-muted" style={{ fontSize: '0.85rem', margin: 0 }}>
                  C’est votre propre compte : vous ne pouvez ni le désactiver ni changer votre rôle.
                </p>
              ) : (
                <>
                  <div className="field">
                    <label htmlFor="role">Rôle</label>
                    <select
                      id="role"
                      value={account.role}
                      disabled={busy}
                      onChange={(e) => changeRole(e.target.value)}
                    >
                      {ROLES.map((r) => <option key={r.value} value={r.value}>{r.label}</option>)}
                    </select>
                  </div>

                  <button
                    className={`btn ${account.is_active ? 'btn--ghost promo-item__danger' : 'btn--primary'}`}
                    style={{ width: '100%' }}
                    disabled={busy}
                    onClick={toggleStatus}
                  >
                    {account.is_active ? 'Désactiver ce compte' : 'Réactiver ce compte'}
                  </button>

                  <p className="text-muted" style={{ fontSize: '0.78rem', marginTop: '0.7rem', marginBottom: 0 }}>
                    Une désactivation coupe l’accès immédiatement, session en cours comprise.
                  </p>

                  <button
                    className="btn btn--ghost promo-item__danger"
                    style={{ width: '100%', marginTop: '1rem' }}
                    disabled={busy}
                    onClick={removeAccount}
                  >
                    Supprimer ce compte
                  </button>

                  <p className="text-muted" style={{ fontSize: '0.78rem', marginTop: '0.5rem', marginBottom: 0 }}>
                    Son historique (projets, investissements, paiements) reste consultable ailleurs.
                  </p>
                </>
              )}
            </div>
          </section>
        </aside>
      </div>

      {confirmDialog}
    </div>
  )
}
