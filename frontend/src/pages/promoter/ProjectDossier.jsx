import { useCallback, useEffect, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import client, { errorMessage } from '../../api/client'
import DossierPanel from '../../components/DossierPanel'
import StatusBadge from '../../components/StatusBadge'
import BackLink from '../../components/BackLink'

/**
 * Dossier d'une opération (niveau 3) : les pièces refaites à chaque projet.
 *
 * Distinct du dossier de l'opérateur (`/verification`), qui ne se remplit
 * qu'une fois. C'est cette checklist-là qui déverrouille la publication du
 * projet — le serveur applique exactement le même calcul.
 */
export default function ProjectDossier() {
  const { id } = useParams()

  const [dossier, setDossier] = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')

  const load = useCallback(async () => {
    try {
      const res = await client.get(`/projects/${id}/dossier`)
      setDossier(res.data)
    } catch (err) {
      setError(errorMessage(err, 'Impossible de charger le dossier de ce projet.'))
    } finally {
      setLoading(false)
    }
  }, [id])

  useEffect(() => { load() }, [load])

  if (loading) return <div className="spinner" />
  if (!dossier) return <div className="container section"><div className="alert alert--error">{error}</div></div>

  const { project, profile_label: profileLabel, progress, complete } = dossier

  return (
    <div className="container">
      <BackLink to="/promoteur/projets" label="Mes projets" />
      <div className="dash-head">
        <p className="eyebrow">Dossier de financement</p>
        <div className="promo-head">
          <h1 style={{ margin: 0 }}>{project.title}</h1>
          <StatusBadge status={project.status} label={project.status_label} />
        </div>
        <p className="text-muted">
          Pièces propres à cette opération, exigées pour la mettre en financement. Elles sont
          distinctes de votre <Link to="/verification">dossier {profileLabel.toLowerCase()}</Link>,
          qui ne se remplit qu’une seule fois.
        </p>

        <div className="dossier-bar" title={`${progress.satisfied} pièces validées sur ${progress.required}`}>
          <div
            className="dossier-bar__fill"
            style={{ width: `${progress.required ? (progress.satisfied / progress.required) * 100 : 0}%` }}
          />
        </div>
      </div>

      {complete ? (
        <div className="alert alert--success">
          Dossier complet. Vous pouvez publier ce projet depuis <Link to="/promoteur/projets">Mes projets</Link>.
        </div>
      ) : (
        <div className="alert alert--info">
          {progress.required - progress.satisfied} pièce{progress.required - progress.satisfied > 1 ? 's' : ''}
          {' '}manque{progress.required - progress.satisfied > 1 ? 'nt' : ''} encore ou attend
          {progress.required - progress.satisfied > 1 ? 'ent' : ''} validation. La publication
          restera bloquée tant que le dossier n’est pas soldé.
        </div>
      )}

      <section className="section" style={{ paddingTop: '1rem' }}>
        <DossierPanel dossier={dossier} projectId={project.id} onChanged={load} />
      </section>
    </div>
  )
}
