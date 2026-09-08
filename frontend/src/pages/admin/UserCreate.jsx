import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import AdminNav from '../../components/AdminNav'
import { errorMessage } from '../../api/client'
import { createUser } from '../../api/admin'

/**
 * Création d'un compte par l'administration (§5).
 *
 * Seule porte d'entrée du rôle interne « Juridique & conformité » : il n'est
 * pas proposé à l'inscription publique, un administrateur doit le créer
 * lui-même. Le mot de passe est fixé ici plutôt que choisi par l'intéressé —
 * c'est à l'administrateur de le lui communiquer par un canal sûr.
 */

const ROLES = [
  { value: 'investor', label: 'Investisseur' },
  { value: 'promoter', label: 'Promoteur' },
  { value: 'legal', label: 'Juridique & conformité' },
  { value: 'admin', label: 'Administrateur' },
]

const EMPTY = {
  first_name: '', last_name: '', email: '', phone: '',
  role: 'investor', password: '', password_confirmation: '',
}

export default function AdminUserCreate() {
  const navigate = useNavigate()
  const [form, setForm] = useState(EMPTY)
  const [error, setError] = useState('')
  const [saving, setSaving] = useState(false)

  function update(e) {
    setForm({ ...form, [e.target.name]: e.target.value })
  }

  async function handleSubmit(e) {
    e.preventDefault()
    setError('')
    setSaving(true)
    try {
      const res = await createUser(form)
      navigate(`/admin/utilisateurs/${res.user.id}`, {
        replace: true,
        state: { notice: res.message },
      })
    } catch (err) {
      setError(errorMessage(err, 'Création impossible.'))
    } finally {
      setSaving(false)
    }
  }

  return (
    <div className="container" style={{ maxWidth: 640, paddingBottom: '3rem' }}>
      <AdminNav
        title="Nouveau compte"
        subtitle="Créé actif, sans dossier KYC — comme une inscription ordinaire."
      />

      {error && <div className="alert alert--error">{error}</div>}

      <form className="card" style={{ padding: '1.5rem' }} onSubmit={handleSubmit}>
        <div className="form-row form-row--2">
          <div className="field">
            <label htmlFor="first_name">Prénom</label>
            <input id="first_name" name="first_name" value={form.first_name} onChange={update} required />
          </div>
          <div className="field">
            <label htmlFor="last_name">Nom</label>
            <input id="last_name" name="last_name" value={form.last_name} onChange={update} required />
          </div>
        </div>

        <div className="field">
          <label htmlFor="email">Email</label>
          <input id="email" name="email" type="email" value={form.email} onChange={update} required />
        </div>

        <div className="field">
          <label htmlFor="phone">Téléphone</label>
          <input
            id="phone" name="phone" type="tel" value={form.phone} onChange={update}
            required placeholder="+221 77 123 45 67"
          />
          <small className="text-muted">Un numéro sénégalais peut être saisi tel quel (77 123 45 67).</small>
        </div>

        <div className="field">
          <label htmlFor="role">Rôle</label>
          <select id="role" name="role" value={form.role} onChange={update}>
            {ROLES.map((r) => <option key={r.value} value={r.value}>{r.label}</option>)}
          </select>
          {form.role === 'legal' && (
            <small className="text-muted">
              Lecture seule sur les projets, tous statuts confondus — aucun droit de modération.
            </small>
          )}
        </div>

        <div className="form-row form-row--2">
          <div className="field">
            <label htmlFor="password">Mot de passe</label>
            <input
              id="password" name="password" type="password" value={form.password}
              onChange={update} required minLength={8} autoComplete="new-password"
            />
          </div>
          <div className="field">
            <label htmlFor="password_confirmation">Confirmation</label>
            <input
              id="password_confirmation" name="password_confirmation" type="password"
              value={form.password_confirmation} onChange={update} required autoComplete="new-password"
            />
          </div>
        </div>

        <button className="btn btn--primary btn--block" disabled={saving}>
          {saving ? 'Création…' : 'Créer le compte'}
        </button>
      </form>
    </div>
  )
}
