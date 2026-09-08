import { useEffect, useState } from 'react'
import { Link, Navigate } from 'react-router-dom'
import client, { errorMessage } from '../api/client'
import { useAuth } from '../context/AuthContext'
import { formatFCFA, formatFCFACompact, formatPercent } from '../utils/format'
import StatusBadge from '../components/StatusBadge'
import {
  AreaChart, BarChart, ChartCard, DataTable, Meter, StackedBar, StatTile, StatusDot,
} from '../components/charts'
import { PRIMARY, RISK_STATUS, SERIES } from '../components/charts/palette'
import { staffHome } from '../utils/roles'

/* ==========================================================================
   Tableau de bord analytique (§7.6) — espace personnel
   Deux lectures d'un même endpoint : l'investisseur suit son portefeuille, le
   promoteur sa collecte. La troisième lecture (plateforme) est administrative
   et vit dans le back-office : pages/admin/Analytics.jsx.
   ========================================================================== */

/* --- Investisseur --------------------------------------------------------- */

function InvestorDashboard({ data }) {
  const { kpis, allocation, timeline, positions } = data

  return (
    <>
      <div className="kpi-row">
        <StatTile label="Total investi" value={formatFCFACompact(kpis.invested)}
          hint={`${kpis.projects_backed} projet${kpis.projects_backed > 1 ? 's' : ''} soutenu${kpis.projects_backed > 1 ? 's' : ''}`} />
        <StatTile label="Rendement estimé" value={formatFCFACompact(kpis.expected_return)}
          hint={`${formatPercent(kpis.weighted_return_rate)} pondérés`} tone="accent" />
        <StatTile label="Valeur à terme" value={formatFCFACompact(kpis.expected_total)}
          hint="Capital + rendement attendu" />
        <StatTile label="Projets soutenus" value={kpis.projects_backed} hint="Opérations distinctes" />
      </div>

      <div className="chart-grid">
        <ChartCard
          title="Capital investi dans le temps"
          subtitle="Cumul de vos placements sur douze mois"
          table={<DataTable
            columns={['Mois', 'Placé dans le mois', 'Cumul']}
            rows={timeline.map((t) => [t.label, formatFCFA(t.amount), formatFCFA(t.cumulative)])}
          />}
        >
          <AreaChart
            data={timeline.map((t) => ({ label: t.label, value: t.cumulative }))}
            format={formatFCFACompact}
          />
        </ChartCard>

        <ChartCard
          title="Répartition du portefeuille"
          subtitle="Par catégorie d'opération"
          table={<DataTable
            columns={['Catégorie', 'Montant']}
            rows={allocation.map((a) => [a.label, formatFCFA(a.value)])}
          />}
        >
          {/* Une seule catégorie ne fait pas une répartition : la phrase
              porte l'information mieux qu'un aplat unique. */}
          {allocation.length === 0 ? (
            <p className="text-muted">Aucun placement pour le moment.</p>
          ) : allocation.length === 1 ? (
            <p className="text-muted">
              La totalité de votre portefeuille est placée en{' '}
              <b>{allocation[0].label.toLowerCase()}</b> ({formatFCFA(allocation[0].value)}).
            </p>
          ) : (
            <StackedBar data={allocation} format={formatFCFACompact} />
          )}
        </ChartCard>
      </div>

      <ChartCard
        title="Mes positions"
        subtitle="Financement de la collecte et avancement du chantier"
        table={<DataTable
          columns={['Projet', 'Montant', 'Part', 'Rendement estimé', 'Statut']}
          rows={positions.map((p) => [
            p.title, formatFCFA(p.amount), `${p.share_percentage} %`,
            formatFCFA(p.estimated_return), p.status_label,
          ])}
        />}
      >
        {positions.length === 0 ? (
          <p className="text-muted">
            Vous n’avez pas encore investi. <Link to="/projets">Découvrir les projets →</Link>
          </p>
        ) : (
          <div className="position-list">
            {positions.map((p) => (
              <div key={p.project_id} className="position">
                <div className="position__head">
                  <Link to={`/projets/${p.project_id}`} className="position__title">{p.title}</Link>
                  <div className="position__meta">
                    <StatusBadge status={p.status} label={p.status_label} />
                    {p.risk_level && (
                      <StatusDot color={RISK_STATUS[p.risk_level]} label={`Risque ${
                        { low: 'faible', medium: 'modéré', high: 'élevé' }[p.risk_level]
                      }`} />
                    )}
                  </div>
                </div>

                <div className="position__figures">
                  <span><b>{formatFCFA(p.amount)}</b> placés · part {p.share_percentage} %</span>
                  <span className="text-muted">Rendement estimé {formatFCFA(p.estimated_return)}</span>
                </div>

                <div className="position__meters">
                  <Meter label="Collecte" value={p.funding_progress} color={PRIMARY} />
                  {p.construction_progress !== null && p.construction_progress !== undefined && (
                    <Meter label="Chantier" value={p.construction_progress} color={SERIES[2]} />
                  )}
                </div>
              </div>
            ))}
          </div>
        )}
      </ChartCard>
    </>
  )
}

/* --- Promoteur ------------------------------------------------------------ */

function PromoterDashboard({ data }) {
  const { kpis, by_project: byProject, timeline, status_breakdown: statuses } = data

  return (
    <>
      <div className="kpi-row">
        <StatTile label="Total collecté" value={formatFCFACompact(kpis.raised)}
          hint={`sur ${formatFCFACompact(kpis.goal)} recherchés`} />
        <StatTile label="Taux de financement" value={`${kpis.funding_rate} %`}
          hint="Tous projets confondus" tone="accent" />
        <StatTile label="Investisseurs" value={kpis.investors} hint="Contributeurs distincts" />
        <StatTile label="Projets en ligne" value={kpis.online_projects}
          hint={`${kpis.projects_total} au total`} />
      </div>

      <div className="chart-grid">
        <ChartCard
          title="Collecte cumulée"
          subtitle="Montants réunis sur vos projets, douze derniers mois"
          table={<DataTable
            columns={['Mois', 'Collecté dans le mois', 'Cumul']}
            rows={timeline.map((t) => [t.label, formatFCFA(t.amount), formatFCFA(t.cumulative)])}
          />}
        >
          <AreaChart
            data={timeline.map((t) => ({ label: t.label, value: t.cumulative }))}
            format={formatFCFACompact}
          />
        </ChartCard>

        <ChartCard
          title="Collecte par projet"
          subtitle="Montant réuni, du plus élevé au plus faible"
          table={<DataTable
            columns={['Projet', 'Collecté', 'Objectif', 'Avancement']}
            rows={byProject.map((p) => [
              p.title, formatFCFA(p.raised), formatFCFA(p.goal), `${p.progress} %`,
            ])}
          />}
        >
          {byProject.length === 0 ? (
            <p className="text-muted">
              Aucun projet. <Link to="/promoteur/projets/nouveau">En créer un →</Link>
            </p>
          ) : (
            <BarChart
              data={byProject.map((p) => ({
                label: p.title,
                value: p.raised,
                note: `${p.progress} % de l’objectif`,
              }))}
              format={formatFCFACompact}
            />
          )}
        </ChartCard>
      </div>

      <div className="chart-grid">
        <ChartCard
          title="Portefeuille de projets"
          subtitle="Répartition par étape du cycle de vie"
          table={<DataTable
            columns={['Statut', 'Projets']}
            rows={statuses.map((s) => [s.label, s.value])}
          />}
        >
          <BarChart
            data={statuses.map((s) => ({ label: s.label, value: s.value }))}
            format={(v) => `${v}`}
          />
        </ChartCard>

        <ChartCard
          title="Avancement des chantiers"
          subtitle="Progression déclarée dans vos rapports"
        >
          {byProject.filter((p) => p.construction_progress !== null).length === 0 ? (
            <p className="text-muted">Aucun chantier démarré.</p>
          ) : (
            <div className="meter-list">
              {byProject
                .filter((p) => p.construction_progress !== null)
                .map((p) => (
                  <Meter
                    key={p.project_id}
                    label={p.title}
                    value={p.construction_progress}
                    caption={`Collecte : ${p.progress} % · score IA ${p.confidence_score ?? '—'}`}
                    color={SERIES[2]}
                  />
                ))}
            </div>
          )}
        </ChartCard>
      </div>
    </>
  )
}

/* --- Page ----------------------------------------------------------------- */

export default function Dashboard() {
  const { user } = useAuth()
  const [data, setData] = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')

  // Ni l'administrateur ni le rôle juridique n'ont d'espace personnel : ni
  // portefeuille, ni projets, ni abonnement. Leur lecture de la plateforme
  // vit dans leur propre console, pas dans un tableau de bord « à eux ».
  const home = staffHome(user.role)

  useEffect(() => {
    if (home) return

    client
      .get('/me/analytics')
      .then((res) => setData(res.data))
      .catch((err) => setError(errorMessage(err, 'Impossible de charger vos statistiques.')))
      .finally(() => setLoading(false))
  }, [home])

  if (home) return <Navigate to={home} replace />

  return (
    <div className="container">
      <div className="dash-head">
        <p className="eyebrow">Espace {user.role_label.toLowerCase()}</p>
        <h1>Bonjour, {user.first_name}</h1>
        <div style={{ display: 'flex', gap: '0.5rem', flexWrap: 'wrap' }}>
          <span className="badge">{user.role_label}</span>
          <Link to="/verification" style={{ textDecoration: 'none' }}>
            <span className={`badge ${user.kyc_verified ? 'badge--risk-low' : 'badge--risk-medium'}`}>
              KYC : {user.kyc_status}
            </span>
          </Link>
          {user.role === 'promoter' && (
            <Link to="/promoteur/abonnement" style={{ textDecoration: 'none' }}>
              <span className={`badge ${user.has_active_subscription ? 'badge--risk-low' : 'badge--risk-high'}`}>
                Abonnement : {user.has_active_subscription ? 'actif' : 'inactif'}
              </span>
            </Link>
          )}
        </div>
      </div>

      {user.role === 'promoter' && !user.has_active_subscription && (
        <div className="alert alert--info">
          Aucun abonnement actif : vos projets ne peuvent pas être publiés.
          {' '}<Link to="/promoteur/abonnement">Voir les paliers →</Link>
        </div>
      )}

      {/* L'administrateur n'atteint jamais cette page (redirigé plus haut) :
          inutile de l'exclure une seconde fois de la relance KYC. */}
      {!user.kyc_verified && (
        <div className="alert alert--info">
          Votre compte n’est pas encore vérifié (KYC). La vérification est requise avant
          {user.role === 'promoter' ? ' de publier un projet.' : ' d’investir.'}
          {' '}<Link to="/verification">Déposer mes pièces →</Link>
        </div>
      )}

      {error && <div className="alert alert--error">{error}</div>}

      {loading ? (
        <div className="spinner" />
      ) : data ? (
        <section className="section" style={{ paddingTop: '0.5rem' }}>
          {data.role === 'investor' && <InvestorDashboard data={data} />}
          {data.role === 'promoter' && <PromoterDashboard data={data} />}
        </section>
      ) : null}
    </div>
  )
}
