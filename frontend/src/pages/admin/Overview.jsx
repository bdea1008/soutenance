import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import AdminNav from '../../components/AdminNav'
import StatusBadge from '../../components/StatusBadge'
import { errorMessage } from '../../api/client'
import { fetchOverview } from '../../api/admin'
import { formatFCFA } from '../../utils/format'

/**
 * Écran d'accueil du back-office.
 *
 * Volontairement tourné vers l'action : ce qui attend une décision en haut,
 * l'activité récente en dessous. Les analyses chiffrées de la plateforme
 * restent sur le tableau de bord (§7.6), on ne les duplique pas ici.
 */

function formatDate(value) {
  if (!value) return '—'
  return new Date(value).toLocaleDateString('fr-FR', {
    day: 'numeric', month: 'short', year: 'numeric',
  })
}

export default function AdminOverview() {
  const [data, setData] = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')

  useEffect(() => {
    fetchOverview()
      .then(setData)
      .catch((err) => setError(errorMessage(err, 'Impossible de charger la console.')))
      .finally(() => setLoading(false))
  }, [])

  return (
    <div className="container">
      <AdminNav
        title="Console d’administration"
        subtitle="Ce qui attend une décision, et les derniers mouvements de la plateforme."
      />

      {error && <div className="alert alert--error">{error}</div>}

      {loading ? (
        <div className="spinner" />
      ) : data ? (
        <div style={{ paddingBottom: '3rem' }}>
          <div className="kpi-row">
            {data.queues.map((queue) => (
              <Link
                key={queue.key}
                to={queue.url}
                className={`stat-tile queue-tile ${queue.tone === 'alert'
                  ? 'stat-tile--alert'
                  : queue.tone === 'accent' ? 'stat-tile--accent' : ''}`}
              >
                <div className="stat-tile__label">{queue.label}</div>
                <div className="stat-tile__value">{queue.value}</div>
                <div className="stat-tile__hint">
                  {queue.value > 0 ? 'Traiter →' : 'Rien à traiter'}
                </div>
              </Link>
            ))}
          </div>

          <div className="chart-grid">
            <section className="card chart-card">
              <div className="chart-card__head">
                <div>
                  <h2 className="chart-card__title">Derniers comptes créés</h2>
                  <p className="chart-card__subtitle">Inscriptions les plus récentes</p>
                </div>
                <Link to="/admin/utilisateurs" className="chart-card__toggle">Tout voir</Link>
              </div>

              {data.recent_users.length === 0 ? (
                <p className="text-muted" style={{ margin: 0 }}>Aucun compte.</p>
              ) : (
                <ul className="admin-feed">
                  {data.recent_users.map((user) => (
                    <li key={user.id} className="admin-feed__item">
                      <div className="admin-feed__main">
                        <Link to={`/admin/utilisateurs/${user.id}`}>{user.name}</Link>
                        <span className="text-muted"> · {user.role_label}</span>
                        <div className="admin-feed__meta">
                          {user.email} · inscrit le {formatDate(user.created_at)}
                        </div>
                      </div>
                      <div className="admin-feed__side">
                        <span className={`badge ${user.kyc_status === 'verified' ? 'badge--risk-low' : ''}`}>
                          {user.kyc_status_label}
                        </span>
                        {!user.is_active && <span className="badge badge--risk-high">Désactivé</span>}
                      </div>
                    </li>
                  ))}
                </ul>
              )}
            </section>

            <section className="card chart-card">
              <div className="chart-card__head">
                <div>
                  <h2 className="chart-card__title">Derniers projets déposés</h2>
                  <p className="chart-card__subtitle">Tous statuts confondus</p>
                </div>
                <Link to="/admin/projets" className="chart-card__toggle">Tout voir</Link>
              </div>

              {data.recent_projects.length === 0 ? (
                <p className="text-muted" style={{ margin: 0 }}>Aucun projet.</p>
              ) : (
                <ul className="admin-feed">
                  {data.recent_projects.map((project) => (
                    <li key={project.id} className="admin-feed__item">
                      <div className="admin-feed__main">
                        <Link to={`/projets/${project.id}`}>{project.title}</Link>
                        <div className="admin-feed__meta">
                          {project.promoter ?? 'Promoteur inconnu'} · financé à {project.funding_progress} %
                        </div>
                      </div>
                      <div className="admin-feed__side">
                        <StatusBadge status={project.status} label={project.status_label} />
                      </div>
                    </li>
                  ))}
                </ul>
              )}
            </section>
          </div>

          <section className="card chart-card">
            <div className="chart-card__head">
              <div>
                <h2 className="chart-card__title">Derniers paiements</h2>
                <p className="chart-card__subtitle">
                  Abonnements et contributions — paiements simulés en MVP
                </p>
              </div>
              <Link to="/admin/finances" className="chart-card__toggle">Suivi financier</Link>
            </div>

            {data.recent_payments.length === 0 ? (
              <p className="text-muted" style={{ margin: 0 }}>Aucun paiement enregistré.</p>
            ) : (
              <div className="chart-table__wrap">
                <table className="chart-table">
                  <thead>
                    <tr>
                      <th>Référence</th>
                      <th>Titulaire</th>
                      <th>Motif</th>
                      <th>Moyen</th>
                      <th>Montant</th>
                      <th>État</th>
                      <th>Date</th>
                    </tr>
                  </thead>
                  <tbody>
                    {data.recent_payments.map((payment) => (
                      <tr key={payment.id}>
                        <td>{payment.reference}</td>
                        <td>{payment.user ?? '—'}</td>
                        <td>{payment.purpose_label}</td>
                        <td>{payment.provider_label}</td>
                        <td>{formatFCFA(payment.amount)}</td>
                        <td>{payment.status_label}</td>
                        <td>{formatDate(payment.created_at)}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </section>
        </div>
      ) : null}
    </div>
  )
}
