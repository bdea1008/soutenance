import { useCallback, useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import AdminNav from '../../components/AdminNav'
import { AreaChart, BarChart, ChartCard, DataTable, StatTile } from '../../components/charts'
import { errorMessage } from '../../api/client'
import { fetchFinanceSummary, fetchPayments } from '../../api/admin'
import { formatFCFA, formatFCFACompact } from '../../utils/format'

/**
 * Suivi financier de la plateforme (§7.4, §16.2.a).
 *
 * Le revenu d'AndTabbax, ce sont les abonnements promoteur ; la collecte des
 * projets transite sans lui appartenir. Les deux sont montrés côte à côte mais
 * jamais additionnés, et la mention « simulé » reste visible partout : en MVP
 * aucun encaissement réel n'a lieu (§16.5).
 *
 * Cet écran ne parle que d'argent. Le parc de contrats (qui est abonné, à quel
 * palier, jusqu'à quand) est tenu par /admin/abonnements : un compteur n'a
 * qu'un seul point d'affichage dans le back-office.
 */

function formatDate(value) {
  if (!value) return '—'
  return new Date(value).toLocaleDateString('fr-FR', { day: 'numeric', month: 'short', year: 'numeric' })
}

/** Le journal mêle deux motifs depuis que l'investissement passe aussi par un paiement (§2). */
const PURPOSES = [
  { value: '', label: 'Tous' },
  { value: 'subscription', label: 'Abonnements' },
  { value: 'contribution', label: 'Contributions' },
]

export default function AdminFinance() {
  const [summary, setSummary] = useState(null)
  const [rows, setRows] = useState([])
  const [holders, setHolders] = useState({})
  const [meta, setMeta] = useState(null)
  const [page, setPage] = useState(1)
  const [purpose, setPurpose] = useState('')
  const [loading, setLoading] = useState(true)
  const [listLoading, setListLoading] = useState(true)
  const [error, setError] = useState('')

  useEffect(() => {
    fetchFinanceSummary()
      .then(setSummary)
      .catch((err) => setError(errorMessage(err, 'Impossible de charger la synthèse financière.')))
      .finally(() => setLoading(false))
  }, [])

  const loadList = useCallback(() => {
    setListLoading(true)

    fetchPayments({ page, per_page: 20, purpose: purpose || undefined })
      .then((res) => {
        setRows(res.data)
        setHolders(res.meta_holders ?? {})
        setMeta(res.meta)
      })
      .catch((err) => setError(errorMessage(err, 'Impossible de charger le journal.')))
      .finally(() => setListLoading(false))
  }, [page, purpose])

  useEffect(() => { loadList() }, [loadList])

  function switchPurpose(value) {
    setPurpose(value)
    setPage(1)
  }

  const kpis = summary?.kpis
  const timeline = summary?.revenue_timeline ?? []
  const byTier = (summary?.revenue_by_tier ?? []).filter((t) => t.value > 0)

  return (
    <div className="container" style={{ paddingBottom: '3rem' }}>
      <AdminNav
        title="Suivi financier"
        subtitle="Revenus d’abonnement, contrats en cours et journal des paiements."
      />

      {error && <div className="alert alert--error">{error}</div>}

      {loading ? (
        <div className="spinner" />
      ) : summary ? (
        <>
          {summary.simulated && (
            <div className="alert alert--info">
              Paiements <b>simulés</b> : la mécanique complète est en place, l’encaissement
              réel (Wave, Orange Money) reste à brancher — cf. §16.5 du cahier des charges.
            </div>
          )}

          <div className="kpi-row">
            <StatTile
              label="Revenu cumulé"
              value={formatFCFACompact(kpis.revenue)}
              hint="Abonnements promoteur, depuis le lancement"
              tone="accent"
            />
            <StatTile
              label="Revenu ce mois-ci"
              value={formatFCFACompact(kpis.revenue_this_month)}
              hint={`${kpis.payments_count} paiement(s) enregistré(s)`}
            />
            <StatTile
              label="Récurrent mensuel"
              value={formatFCFACompact(kpis.monthly_recurring)}
              hint="Somme des abonnements en cours — le parc est détaillé dans l’onglet Abonnements"
            />
          </div>

          {/* La collecte n'est pas un revenu : elle est présentée à part, avec
              sa nature explicitée, pour qu'on ne la confonde pas avec le CA. */}
          <div className="card" style={{ padding: '1.1rem 1.3rem', marginBottom: '1rem' }}>
            <div className="stat-tile__label">Volume investi sur les projets</div>
            <div className="stat-tile__value">{formatFCFA(kpis.contributions_volume)}</div>
            <div className="stat-tile__hint">
              Transite par la plateforme sans lui appartenir — investir est sans frais
              pour l’investisseur. Ce montant ne s’ajoute pas au revenu.
            </div>
          </div>

          <div className="chart-grid">
            <ChartCard
              title="Revenu d’abonnement dans le temps"
              subtitle="Cumul sur douze mois, en FCFA"
              table={<DataTable
                columns={['Mois', 'Encaissé dans le mois', 'Cumul']}
                rows={timeline.map((t) => [t.label, formatFCFA(t.amount), formatFCFA(t.cumulative)])}
              />}
            >
              <AreaChart
                data={timeline.map((t) => ({ label: t.label, value: t.cumulative }))}
                format={formatFCFACompact}
              />
            </ChartCard>

            <ChartCard
              title="Revenu par palier"
              subtitle="Ce que rapporte chaque niveau d’abonnement"
              table={<DataTable
                columns={['Palier', 'Abonnements', 'Dont actifs', 'Revenu']}
                rows={(summary.revenue_by_tier ?? []).map((t) => [
                  t.label, t.subscriptions, t.active, formatFCFA(t.value),
                ])}
              />}
            >
              {byTier.length === 0 ? (
                <p className="text-muted">Aucun abonnement souscrit à ce jour.</p>
              ) : (
                <BarChart
                  data={byTier.map((t) => ({
                    label: t.label,
                    value: t.value,
                    note: `${t.active} actif(s) sur ${t.subscriptions}`,
                  }))}
                  format={formatFCFACompact}
                />
              )}
            </ChartCard>
          </div>

          {/* --- Journal des paiements --- */}
          <section className="card chart-card">
            <div className="chart-card__head">
              <div>
                <h2 className="chart-card__title">Journal des paiements</h2>
                <p className="chart-card__subtitle">
                  Historique des transactions (§7.4), du plus récent au plus ancien
                </p>
              </div>
              <Link to="/admin/abonnements" className="chart-card__toggle">
                Voir les abonnements
              </Link>
            </div>

            <div className="admin-chips" style={{ marginBottom: '1.1rem' }}>
              {PURPOSES.map((p) => (
                <button
                  key={p.value}
                  className={`admin-chip ${purpose === p.value ? 'admin-chip--on' : ''}`}
                  onClick={() => switchPurpose(p.value)}
                >
                  {p.label}
                </button>
              ))}
            </div>

            {listLoading ? (
              <div className="spinner" />
            ) : rows.length === 0 ? (
              <p className="text-muted" style={{ margin: 0 }}>Aucune ligne enregistrée.</p>
            ) : (
              <div className="chart-table__wrap">
                <table className="chart-table admin-table">
                  <thead>
                    <tr>
                      <th>Référence</th><th>Titulaire</th><th>Motif</th>
                      <th>Moyen</th><th>Montant</th><th>État</th><th>Date</th>
                    </tr>
                  </thead>
                  <tbody>
                    {rows.map((row) => (
                      <tr key={row.id}>
                        <td>{row.reference}</td>
                        <td>{holder(holders, row.id)}</td>
                        <td>{row.purpose_label}</td>
                        <td>{row.provider_label}</td>
                        <td>{formatFCFA(row.amount)}</td>
                        <td>{row.status_label}</td>
                        <td>{formatDate(row.paid_at ?? row.created_at)}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}

            {meta && meta.last_page > 1 && (
              <div className="admin-pager">
                <button className="btn btn--ghost" disabled={page <= 1} onClick={() => setPage(page - 1)}>
                  Précédent
                </button>
                <span className="text-muted">
                  Page {meta.current_page} sur {meta.last_page} · {meta.total} paiement(s)
                </span>
                <button
                  className="btn btn--ghost"
                  disabled={page >= meta.last_page}
                  onClick={() => setPage(page + 1)}
                >
                  Suivant
                </button>
              </div>
            )}
          </section>
        </>
      ) : null}
    </div>
  )
}

/**
 * Titulaire d'un paiement. PaymentResource est partagée avec l'espace
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
