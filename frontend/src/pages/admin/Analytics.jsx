import { useEffect, useState } from 'react'
import AdminNav from '../../components/AdminNav'
import { AreaChart, BarChart, ChartCard, DataTable } from '../../components/charts'
import { KYC_STATUS } from '../../components/charts/palette'
import client, { errorMessage } from '../../api/client'
import { formatFCFA, formatFCFACompact } from '../../utils/format'

/**
 * Analyses de la plateforme (§7.6, lecture administrateur).
 *
 * Vivait auparavant dans le tableau de bord personnel, où elle n'avait pas sa
 * place : « Tableau de bord » désigne pour les deux autres rôles leur espace
 * à eux, alors que cette lecture-ci porte sur la plateforme entière. Elle est
 * donc une section du back-office.
 *
 * Pas de tuiles de chiffres ici : les compteurs sont portés par la console,
 * l'annuaire et le suivi financier. Cette page ne fait que ce que les autres
 * ne font pas — montrer des tendances et des répartitions.
 */
export default function AdminAnalytics() {
  const [data, setData] = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')

  useEffect(() => {
    client
      .get('/me/analytics')
      .then((res) => setData(res.data))
      .catch((err) => setError(errorMessage(err, 'Impossible de charger les analyses.')))
      .finally(() => setLoading(false))
  }, [])

  const timeline = data?.timeline ?? []
  const regions = data?.by_region ?? []
  const kyc = data?.kyc_funnel ?? []
  const statuses = data?.status_breakdown ?? []

  return (
    <div className="container" style={{ paddingBottom: '3rem' }}>
      <AdminNav
        title="Analyses de la plateforme"
        subtitle="Tendances et répartitions, tous projets et tous comptes confondus."
      />

      {error && <div className="alert alert--error">{error}</div>}

      {loading ? (
        <div className="spinner" />
      ) : data ? (
        <>
          <div className="chart-grid">
            <ChartCard
              title="Collecte cumulée de la plateforme"
              subtitle="Tous projets confondus, douze derniers mois"
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
              title="Collecte par région"
              subtitle="Où l’argent se place"
              table={<DataTable
                columns={['Région', 'Collecté', 'Projets']}
                rows={regions.map((r) => [r.label, formatFCFA(r.value), r.projects])}
              />}
            >
              {regions.length === 0 ? (
                <p className="text-muted">Aucune collecte enregistrée.</p>
              ) : (
                <BarChart
                  data={regions.map((r) => ({
                    label: r.label,
                    value: r.value,
                    note: `${r.projects} projet${r.projects > 1 ? 's' : ''}`,
                  }))}
                  format={formatFCFACompact}
                />
              )}
            </ChartCard>
          </div>

          <div className="chart-grid">
            <ChartCard
              title="Vérification des comptes"
              subtitle="Répartition des utilisateurs par état KYC"
              table={<DataTable
                columns={['État', 'Comptes']}
                rows={kyc.map((k) => [k.label, k.value])}
              />}
              footer="Les couleurs d’état ne portent jamais seules l’information : chaque barre est nommée."
            >
              <BarChart
                data={kyc.map((k) => ({
                  label: k.label,
                  value: k.value,
                  color: KYC_STATUS[k.status],
                }))}
                format={(v) => `${v}`}
              />
            </ChartCard>

            <ChartCard
              title="Projets par statut"
              subtitle="État du portefeuille de la plateforme"
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
          </div>
        </>
      ) : null}
    </div>
  )
}
