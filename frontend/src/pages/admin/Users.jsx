import { useCallback, useEffect, useState } from 'react'
import { Link, useLocation, useSearchParams } from 'react-router-dom'
import AdminNav from '../../components/AdminNav'
import { errorMessage } from '../../api/client'
import { fetchUserStats, fetchUsers, setUserStatus } from '../../api/admin'
import { formatFCFA } from '../../utils/format'
import useConfirm from '../../hooks/useConfirm'

/**
 * Annuaire des comptes (§5, supervision).
 *
 * Les filtres vivent dans l'URL : un administrateur qui envoie « regarde les
 * comptes suspendus » doit pouvoir coller un lien, et la console y renvoie
 * directement (/admin/utilisateurs?actif=0).
 */

const ROLES = [
  { value: '', label: 'Tous les rôles' },
  { value: 'investor', label: 'Investisseurs' },
  { value: 'promoter', label: 'Promoteurs' },
  { value: 'admin', label: 'Administrateurs' },
  { value: 'legal', label: 'Juridique & conformité' },
]

const KYC = [
  { value: '', label: 'Tout état KYC' },
  { value: 'none', label: 'Non vérifié' },
  { value: 'pending', label: 'En attente' },
  { value: 'verified', label: 'Vérifié' },
  { value: 'rejected', label: 'Rejeté' },
]

const ACTIVITY = [
  { value: '', label: 'Actifs et désactivés' },
  { value: '1', label: 'Comptes actifs' },
  { value: '0', label: 'Comptes désactivés' },
]

const KYC_TONE = { verified: 'badge--risk-low', pending: 'badge--risk-medium', rejected: 'badge--risk-high' }

export default function AdminUsers() {
  const { confirm, confirmDialog } = useConfirm()
  const [params, setParams] = useSearchParams()
  const location = useLocation()
  const [users, setUsers] = useState([])
  const [meta, setMeta] = useState(null)
  const [stats, setStats] = useState(null)
  const [loading, setLoading] = useState(true)
  const [busyId, setBusyId] = useState(null)
  const [error, setError] = useState('')
  // Message transmis par la création ou la suppression d'un compte.
  const [notice, setNotice] = useState(location.state?.notice ?? '')

  // Champ de recherche piloté localement puis poussé dans l'URL à la
  // validation : filtrer à chaque frappe déclencherait une requête par lettre.
  const [search, setSearch] = useState(params.get('q') ?? '')

  const role = params.get('role') ?? ''
  const kyc = params.get('kyc') ?? ''
  const active = params.get('actif') ?? ''
  const page = Number(params.get('page') ?? 1)
  const query = params.get('q') ?? ''

  const load = useCallback(() => {
    setLoading(true)
    Promise.all([
      fetchUsers({
        role: role || undefined,
        kyc_status: kyc || undefined,
        active: active === '' ? undefined : active,
        search: query || undefined,
        page,
        per_page: 20,
      }),
      fetchUserStats(),
    ])
      .then(([list, counters]) => {
        setUsers(list.data)
        setMeta(list.meta)
        setStats(counters)
      })
      .catch((err) => setError(errorMessage(err, 'Impossible de charger l’annuaire.')))
      .finally(() => setLoading(false))
  }, [role, kyc, active, query, page])

  useEffect(() => { load() }, [load])

  /** Met à jour un filtre et remet la pagination à la première page. */
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

  async function toggleStatus(user) {
    const suspending = user.is_active
    let reason

    if (suspending) {
      // Motif exigé par l'API, donc exigé dans la fenêtre : la valider sans
      // motif la rouvrirait aussitôt sur une erreur.
      reason = await confirm({
        tone: 'danger',
        icon: 'ban',
        eyebrow: 'Compte',
        title: 'Suspendre ce compte ?',
        subtitle: `${user.name} · ${user.email}`,
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
      subtitle: `${user.name} · ${user.email}`,
      message: 'Le titulaire pourra de nouveau se connecter et retrouvera l’ensemble de ses droits.',
      confirmLabel: 'Réactiver',
    })) {
      return
    }

    setBusyId(user.id)
    setError('')
    setNotice('')
    try {
      const res = await setUserStatus(user.id, !suspending, reason)
      setNotice(`${res.message} — ${user.name}.`)
      load()
    } catch (err) {
      setError(errorMessage(err, 'Action impossible.'))
    } finally {
      setBusyId(null)
    }
  }

  return (
    <div className="container">
      <AdminNav
        title="Utilisateurs"
        subtitle="Annuaire des comptes : vérification, activité et supervision."
        action={<Link to="/admin/utilisateurs/nouveau" className="btn btn--primary">+ Nouveau compte</Link>}
      />

      {stats && (
        <div className="kpi-row">
          <div className="stat-tile">
            <div className="stat-tile__label">Comptes</div>
            <div className="stat-tile__value">{stats.total}</div>
            <div className="stat-tile__hint">{stats.investors} investisseurs · {stats.promoters} promoteurs</div>
          </div>
          <div className="stat-tile">
            <div className="stat-tile__label">Vérifiés (KYC)</div>
            <div className="stat-tile__value">{stats.kyc_verified}</div>
            <div className="stat-tile__hint">niveau 3 du parcours</div>
          </div>
          <div className={`stat-tile ${stats.kyc_pending > 0 ? 'stat-tile--accent' : ''}`}>
            <div className="stat-tile__label">En attente de vérification</div>
            <div className="stat-tile__value">{stats.kyc_pending}</div>
            <div className="stat-tile__hint">
              <Link to="/admin/documents">Examiner les pièces</Link>
            </div>
          </div>
          <div className={`stat-tile ${stats.suspended > 0 ? 'stat-tile--alert' : ''}`}>
            <div className="stat-tile__label">Comptes désactivés</div>
            <div className="stat-tile__value">{stats.suspended}</div>
            <div className="stat-tile__hint">{stats.admins} administrateur(s)</div>
          </div>
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
          placeholder="Nom, email ou téléphone"
          value={search}
          onChange={(e) => setSearch(e.target.value)}
        />
        <select value={role} onChange={(e) => updateFilter('role', e.target.value)}>
          {ROLES.map((r) => <option key={r.value} value={r.value}>{r.label}</option>)}
        </select>
        <select value={kyc} onChange={(e) => updateFilter('kyc', e.target.value)}>
          {KYC.map((k) => <option key={k.value} value={k.value}>{k.label}</option>)}
        </select>
        <select value={active} onChange={(e) => updateFilter('actif', e.target.value)}>
          {ACTIVITY.map((a) => <option key={a.value} value={a.value}>{a.label}</option>)}
        </select>
        <button className="btn btn--primary" type="submit">Rechercher</button>
      </form>

      {loading ? (
        <div className="spinner" />
      ) : users.length === 0 ? (
        <div className="card" style={{ padding: '2rem', textAlign: 'center' }}>
          <p className="text-muted" style={{ margin: 0 }}>Aucun compte ne correspond à ces critères.</p>
        </div>
      ) : (
        <div className="card" style={{ marginBottom: '1rem' }}>
          <div className="chart-table__wrap">
            <table className="chart-table admin-table">
              <thead>
                <tr>
                  <th>Compte</th>
                  <th>Rôle</th>
                  <th>KYC</th>
                  <th>Activité</th>
                  <th>Volume</th>
                  <th>État</th>
                  <th />
                </tr>
              </thead>
              <tbody>
                {users.map((user) => (
                  <tr key={user.id}>
                    <td>
                      <Link to={`/admin/utilisateurs/${user.id}`}>{user.name}</Link>
                      <div className="text-muted" style={{ fontSize: '0.78rem' }}>{user.email}</div>
                    </td>
                    <td>
                      {user.role_label}
                      {user.subscription && (
                        <div className="text-muted" style={{ fontSize: '0.78rem' }}>
                          {user.subscription.tier_label}
                        </div>
                      )}
                    </td>
                    <td>
                      <span className={`badge ${KYC_TONE[user.kyc_status] ?? ''}`}>
                        {user.kyc_status_label}
                      </span>
                      {user.pending_documents_count > 0 && (
                        <div className="text-muted" style={{ fontSize: '0.78rem' }}>
                          {user.pending_documents_count} pièce(s) à examiner
                        </div>
                      )}
                    </td>
                    <td>
                      {user.role === 'promoter' && `${user.projects_count} projet(s)`}
                      {user.role === 'investor' && `${user.contributions_count} investissement(s)`}
                      {(user.role === 'admin' || user.role === 'legal') && '—'}
                    </td>
                    <td>
                      {user.role === 'promoter' && formatFCFA(user.raised_total ?? 0)}
                      {user.role === 'investor' && formatFCFA(user.invested_total ?? 0)}
                      {(user.role === 'admin' || user.role === 'legal') && '—'}
                    </td>
                    <td>
                      <span className={`badge ${user.is_active ? 'badge--risk-low' : 'badge--risk-high'}`}>
                        {user.is_active ? 'Actif' : 'Désactivé'}
                      </span>
                    </td>
                    <td style={{ textAlign: 'right' }}>
                      <button
                        className={`btn btn--ghost ${user.is_active ? 'promo-item__danger' : ''}`}
                        style={{ padding: '0.3rem 0.7rem', fontSize: '0.82rem' }}
                        disabled={busyId === user.id}
                        onClick={() => toggleStatus(user)}
                      >
                        {user.is_active ? 'Désactiver' : 'Réactiver'}
                      </button>
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
            Page {meta.current_page} sur {meta.last_page} · {meta.total} compte(s)
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
