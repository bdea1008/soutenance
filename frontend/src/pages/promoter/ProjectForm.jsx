import { useEffect, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import client, { errorMessage } from '../../api/client'
import BackLink from '../../components/BackLink'

/** Régions du Sénégal proposées à la saisie. */
const REGIONS = [
  'Dakar', 'Thiès', 'Diourbel', 'Saint-Louis', 'Ziguinchor', 'Kaolack',
  'Fatick', 'Louga', 'Matam', 'Tambacounda', 'Kolda', 'Kaffrine',
  'Kédougou', 'Sédhiou',
]

const CATEGORIES = [
  { value: 'residentiel', label: 'Résidentiel' },
  { value: 'commercial', label: 'Commercial' },
  { value: 'terrain', label: 'Terrain / lotissement' },
  { value: 'touristique', label: 'Touristique' },
  { value: 'mixte', label: 'Mixte' },
]

const EMPTY = {
  title: '', summary: '', description: '', category: 'residentiel',
  region: 'Dakar', city: '', address: '',
  funding_goal: '', min_investment: '', expected_return_rate: '', duration_months: '',
  cover_image: '',
}

/** Ne transmet que les champs renseignés — l'API attend `null`/absence, pas ''. */
function toPayload(form) {
  const payload = {}
  for (const [key, value] of Object.entries(form)) {
    if (value !== '' && value !== null) payload[key] = value
  }
  // Champs numériques : l'API valide des entiers/décimaux, pas des chaînes.
  for (const key of ['funding_goal', 'min_investment', 'duration_months']) {
    if (payload[key] !== undefined) payload[key] = Number(payload[key])
  }
  if (payload.expected_return_rate !== undefined) {
    payload.expected_return_rate = Number(payload.expected_return_rate)
  }
  return payload
}

export default function ProjectForm() {
  const { id } = useParams()
  const navigate = useNavigate()
  const isEdit = Boolean(id)

  const [form, setForm] = useState(EMPTY)
  const [loading, setLoading] = useState(isEdit)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState('')

  // En édition, on pré-remplit depuis le détail du projet.
  useEffect(() => {
    if (!isEdit) return
    client
      .get(`/projects/${id}`)
      .then((res) => {
        const p = res.data.project
        setForm({
          title: p.title ?? '',
          summary: p.summary ?? '',
          description: p.description ?? '',
          category: p.category ?? '',
          region: p.location.region ?? '',
          city: p.location.city ?? '',
          address: p.location.address ?? '',
          funding_goal: p.financials.funding_goal ?? '',
          min_investment: p.financials.min_investment ?? '',
          expected_return_rate: p.financials.expected_return_rate ?? '',
          duration_months: p.financials.duration_months ?? '',
          cover_image: p.cover_image ?? '',
        })
      })
      .catch((err) => setError(errorMessage(err, 'Projet introuvable.')))
      .finally(() => setLoading(false))
  }, [id, isEdit])

  function update(e) {
    setForm((prev) => ({ ...prev, [e.target.name]: e.target.value }))
  }

  async function handleSubmit(e) {
    e.preventDefault()
    setError('')
    setSaving(true)
    try {
      const payload = toPayload(form)
      if (isEdit) {
        await client.put(`/projects/${id}`, payload)
      } else {
        await client.post('/projects', payload)
      }
      navigate('/promoteur/projets', { replace: true })
    } catch (err) {
      setError(errorMessage(err, 'Enregistrement impossible.'))
      window.scrollTo({ top: 0, behavior: 'smooth' })
    } finally {
      setSaving(false)
    }
  }

  if (loading) return <div className="spinner" />

  return (
    <div className="container section">
      <BackLink to="/promoteur/projets" label="Mes projets" />
      <div className="section__head">
        <p className="eyebrow">Espace promoteur</p>
        <h1 style={{ margin: 0 }}>{isEdit ? 'Modifier le projet' : 'Nouveau projet'}</h1>
        <p className="text-muted">
          {isEdit
            ? 'Les modifications sont enregistrées immédiatement.'
            : 'Le projet est enregistré en brouillon. Vous pourrez le publier depuis « Mes projets ».'}
        </p>
      </div>

      {error && <div className="alert alert--error">{error}</div>}

      <form onSubmit={handleSubmit} className="card" style={{ padding: '1.5rem', maxWidth: 820 }}>
        <h3 style={{ marginTop: 0 }}>Présentation</h3>

        <div className="field">
          <label htmlFor="title">Titre du projet *</label>
          <input id="title" name="title" value={form.title} onChange={update} required maxLength={180}
            placeholder="Ex : Résidence Les Almadies" />
        </div>

        <div className="field">
          <label htmlFor="summary">Résumé court</label>
          <input id="summary" name="summary" value={form.summary} onChange={update} maxLength={280}
            placeholder="Une phrase d’accroche (280 caractères max)" />
        </div>

        <div className="field">
          <label htmlFor="description">Description détaillée</label>
          <textarea id="description" name="description" value={form.description} onChange={update} rows={6}
            placeholder="Nature du bien, avancement, garanties, modalités de sortie…" />
        </div>

        <div className="field">
          <label htmlFor="category">Catégorie</label>
          <select id="category" name="category" value={form.category} onChange={update}>
            {CATEGORIES.map((c) => <option key={c.value} value={c.value}>{c.label}</option>)}
          </select>
        </div>

        <h3>Localisation</h3>
        <div className="form-row form-row--2">
          <div className="field">
            <label htmlFor="region">Région</label>
            <select id="region" name="region" value={form.region} onChange={update}>
              {REGIONS.map((r) => <option key={r} value={r}>{r}</option>)}
            </select>
          </div>
          <div className="field">
            <label htmlFor="city">Ville / commune</label>
            <input id="city" name="city" value={form.city} onChange={update} maxLength={100} />
          </div>
        </div>
        <div className="field">
          <label htmlFor="address">Adresse</label>
          <input id="address" name="address" value={form.address} onChange={update} maxLength={255} />
        </div>

        <h3>Financement</h3>
        <div className="form-row form-row--2">
          <div className="field">
            <label htmlFor="funding_goal">Objectif de collecte (FCFA) *</label>
            <input id="funding_goal" name="funding_goal" type="number" min={100000} step={1000}
              value={form.funding_goal} onChange={update} required />
            <small className="text-muted">Minimum 100 000 FCFA.</small>
          </div>
          <div className="field">
            <label htmlFor="min_investment">Ticket minimum (FCFA)</label>
            <input id="min_investment" name="min_investment" type="number" min={0} step={1000}
              value={form.min_investment} onChange={update} />
          </div>
        </div>

        <div className="form-row form-row--2">
          <div className="field">
            <label htmlFor="expected_return_rate">Rendement attendu (%)</label>
            <input id="expected_return_rate" name="expected_return_rate" type="number" min={0} max={100} step="0.1"
              value={form.expected_return_rate} onChange={update} />
          </div>
          <div className="field">
            <label htmlFor="duration_months">Durée du placement (mois)</label>
            <input id="duration_months" name="duration_months" type="number" min={1} max={120}
              value={form.duration_months} onChange={update} />
          </div>
        </div>

        <h3>Visuel</h3>
        <div className="field">
          <label htmlFor="cover_image">Image de couverture (URL)</label>
          <input id="cover_image" name="cover_image" value={form.cover_image} onChange={update} maxLength={255}
            placeholder="https://…" />
          <small className="text-muted">Le dépôt de fichiers arrivera avec le module documents.</small>
        </div>

        <div style={{ display: 'flex', gap: '0.75rem', marginTop: '1.5rem', flexWrap: 'wrap' }}>
          <button className="btn btn--primary" disabled={saving}>
            {saving ? 'Enregistrement…' : isEdit ? 'Enregistrer les modifications' : 'Créer le brouillon'}
          </button>
          <Link to="/promoteur/projets" className="btn btn--ghost">Annuler</Link>
        </div>
      </form>
    </div>
  )
}
