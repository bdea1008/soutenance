import { useCallback, useEffect, useRef, useState } from 'react'
import { useParams } from 'react-router-dom'
import client, { errorMessage } from '../../api/client'
import { fetchReports, reportFormData } from '../../api/reports'
import SiteReportTimeline from '../../components/SiteReportTimeline'
import BackLink from '../../components/BackLink'
import useConfirm from '../../hooks/useConfirm'

const MAX_PHOTOS = 6

/** Date du jour au format attendu par un `<input type="date">`. */
function today() {
  return new Date().toISOString().slice(0, 10)
}

const EMPTY = { title: '', description: '', progress_percentage: '', reported_at: today() }

/**
 * Espace promoteur — journal de chantier d'un projet (§7.5) : publier un
 * rapport d'avancement, le corriger, le retirer.
 */
export default function ProjectReports() {
  const { id } = useParams()
  const fileInput = useRef(null)

  const [project, setProject] = useState(null)
  const [reports, setReports] = useState([])
  const [form, setForm] = useState(EMPTY)
  const [editing, setEditing] = useState(null)
  const [photos, setPhotos] = useState([])
  const [loading, setLoading] = useState(true)
  const [busy, setBusy] = useState(false)
  const [busyId, setBusyId] = useState(null)
  const { confirm, confirmDialog } = useConfirm()
  const [error, setError] = useState('')
  const [notice, setNotice] = useState('')

  const load = useCallback(() => {
    Promise.all([client.get(`/projects/${id}`), fetchReports(id, { per_page: 50 })])
      .then(([detail, journal]) => {
        setProject(detail.data.project)
        setReports(journal.data)
      })
      .catch((err) => setError(errorMessage(err, 'Projet introuvable.')))
      .finally(() => setLoading(false))
  }, [id])

  useEffect(() => { load() }, [load])

  function resetForm() {
    setEditing(null)
    setForm(EMPTY)
    setPhotos([])
    if (fileInput.current) fileInput.current.value = ''
  }

  function startEdit(report) {
    setEditing(report)
    setForm({
      title: report.title,
      description: report.description || '',
      progress_percentage: report.progress_percentage,
      reported_at: report.reported_at ? report.reported_at.slice(0, 10) : today(),
    })
    setPhotos([])
    if (fileInput.current) fileInput.current.value = ''
    setNotice('')
    setError('')
    window.scrollTo({ top: 0, behavior: 'smooth' })
  }

  async function submit(e) {
    e.preventDefault()
    setBusy(true)
    setError('')
    setNotice('')

    const payload = reportFormData({ ...form, photos })

    try {
      const res = editing
        // Laravel ne lit pas le multipart d'un vrai PUT : on passe par _method.
        ? await client.post(`/reports/${editing.id}?_method=PUT`, payload)
        : await client.post(`/projects/${id}/reports`, payload)

      setNotice(res.data.message)
      resetForm()
      load()
    } catch (err) {
      setError(errorMessage(err, 'Publication impossible.'))
    } finally {
      setBusy(false)
    }
  }

  async function remove(report) {
    const confirmed = await confirm({
      tone: 'danger',
      icon: 'ban',
      eyebrow: 'Suppression',
      title: 'Retirer ce rapport de chantier ?',
      subtitle: report.title,
      message: 'Les investisseurs du projet ne le verront plus dans le journal, photos comprises. '
        + 'L’avancement affiché repartira du rapport précédent.',
      confirmLabel: 'Retirer le rapport',
      confirmTone: 'danger',
    })

    if (!confirmed) return
    setBusyId(report.id)
    setError('')
    setNotice('')
    try {
      const res = await client.delete(`/reports/${report.id}`)
      setNotice(res.data.message)
      if (editing?.id === report.id) resetForm()
      load()
    } catch (err) {
      setError(errorMessage(err, 'Suppression impossible.'))
    } finally {
      setBusyId(null)
    }
  }

  function pickPhotos(e) {
    const chosen = Array.from(e.target.files ?? [])
    const room = MAX_PHOTOS - (editing?.photos.length ?? 0)

    if (chosen.length > room) {
      setError(`Six photos au maximum par rapport (${room} place${room > 1 ? 's' : ''} restante${room > 1 ? 's' : ''}).`)
      e.target.value = ''
      setPhotos([])
      return
    }

    setError('')
    setPhotos(chosen)
  }

  if (loading) return <div className="spinner" />
  if (!project) return <div className="container section"><div className="alert alert--error">{error}</div></div>

  const lastProgress = reports.length > 0 ? Math.max(...reports.map((r) => r.progress_percentage)) : 0
  const canReport = ['funded', 'in_progress', 'completed'].includes(project.status)

  return (
    <div className="container">
      <BackLink to="/promoteur/projets" label="Mes projets" />
      <div className="dash-head">
        <p className="eyebrow">Suivi de chantier</p>
        <div className="promo-head">
          <h1 style={{ margin: 0 }}>Chantier — {project.title}</h1>
          <span className="badge">{project.status_label}</span>
        </div>
        <p className="text-muted">
          Rendez compte de l’avancement à vos investisseurs. Chaque rapport est visible
          par les utilisateurs de la plateforme et alimente la barre de progression du projet.
        </p>
      </div>

      {error && <div className="alert alert--error">{error}</div>}
      {notice && <div className="alert alert--success">{notice}</div>}

      {!canReport ? (
        <div className="alert alert--info">
          Le suivi de chantier s’ouvre une fois le financement bouclé
          (statut actuel : {project.status_label}).
        </div>
      ) : (
        <section className="section" style={{ paddingTop: 0 }}>
          <h2 style={{ fontSize: '1.3rem' }}>
            {editing ? 'Corriger le rapport' : 'Publier un rapport'}
          </h2>

          <form onSubmit={submit} className="card" style={{ padding: '1.5rem', maxWidth: 680 }}>
            <div className="field">
              <label htmlFor="title">Titre</label>
              <input
                id="title" required maxLength={150} value={form.title}
                placeholder="Ex : Élévation du deuxième niveau"
                onChange={(e) => setForm({ ...form, title: e.target.value })}
              />
            </div>

            <div className="field">
              <label htmlFor="description">Description</label>
              <textarea
                id="description" rows={4} maxLength={5000} value={form.description}
                placeholder="Travaux réalisés, difficultés rencontrées, prochaines étapes…"
                onChange={(e) => setForm({ ...form, description: e.target.value })}
              />
            </div>

            <div className="grid grid--2">
              <div className="field">
                <label htmlFor="progress">Avancement (%)</label>
                <input
                  id="progress" type="number" required min={editing ? 0 : lastProgress} max={100}
                  value={form.progress_percentage}
                  onChange={(e) => setForm({ ...form, progress_percentage: e.target.value })}
                />
                {!editing && lastProgress > 0 && (
                  <small className="text-muted">Dernier rapport : {lastProgress} % — un chantier ne recule pas.</small>
                )}
              </div>

              <div className="field">
                <label htmlFor="reported_at">Date du constat</label>
                <input
                  id="reported_at" type="date" required max={today()} value={form.reported_at}
                  onChange={(e) => setForm({ ...form, reported_at: e.target.value })}
                />
              </div>
            </div>

            <div className="field">
              <label htmlFor="photos">Photos {editing && '(s’ajoutent aux existantes)'}</label>
              <input
                id="photos" type="file" multiple ref={fileInput}
                accept=".jpg,.jpeg,.png,.webp"
                onChange={pickPhotos}
              />
              <small className="text-muted">
                JPG, PNG ou WEBP — 6 photos maximum par rapport, 5 Mo chacune.
                {photos.length > 0 && ` ${photos.length} sélectionnée${photos.length > 1 ? 's' : ''}.`}
              </small>
            </div>

            <div className="promo-item__actions">
              <button className="btn btn--primary" disabled={busy}>
                {busy ? 'Envoi…' : editing ? 'Enregistrer les corrections' : 'Publier le rapport'}
              </button>
              {editing && (
                <button type="button" className="btn btn--ghost" onClick={resetForm}>
                  Annuler
                </button>
              )}
            </div>
          </form>
        </section>
      )}

      <section className="section" style={{ paddingTop: 0 }}>
        <h2 style={{ fontSize: '1.3rem' }}>
          Journal d’avancement <span className="text-muted">({reports.length})</span>
        </h2>
        <SiteReportTimeline
          reports={reports}
          onEdit={startEdit}
          onDelete={remove}
          busyId={busyId}
        />
      </section>

      {confirmDialog}
    </div>
  )
}
