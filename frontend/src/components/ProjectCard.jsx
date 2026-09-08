import { Link } from 'react-router-dom'
import { formatDate, formatFCFACompact } from '../utils/format'
import RiskBadge from './RiskBadge'
import Icon from './Icon'
import logoMark from '../assets/logo-mark.png'

/**
 * Carte d'aperçu d'un projet (page d'accueil et liste publique).
 * Le lien mène au détail — réservé aux utilisateurs authentifiés (niveau 2).
 */
export default function ProjectCard({ project }) {
  const progress = project.funding_progress ?? 0

  return (
    <Link to={`/projets/${project.id}`} className="card project-card">
      <div className="project-card__media">
        {project.cover_image ? (
          <img src={project.cover_image} alt={project.title} style={{ width: '100%', height: '100%', objectFit: 'cover' }} />
        ) : (
          // Faute de photo, la marque en filigrane plutôt qu'un pictogramme :
          // la plupart des projets seedés n'ont pas d'illustration, ces cartes
          // sont donc ce qu'on voit le plus dans l'application.
          <img src={logoMark} alt="" className="project-card__fallback" />
        )}
        {project.category && <span className="badge project-card__cat">{project.category}</span>}
      </div>

      <div className="project-card__body">
        <div style={{ display: 'flex', gap: '0.4rem', flexWrap: 'wrap' }}>
          <span className="badge">{project.status_label}</span>
          {project.risk_level && <RiskBadge level={project.risk_level} />}
        </div>

        <h3 className="project-card__title">{project.title}</h3>
        <p className="project-card__loc"><Icon name="pin" size={15} /> {project.city}{project.region ? `, ${project.region}` : ''}</p>

        <div className="progress" aria-label={`Financé à ${progress}%`}>
          <span style={{ width: `${Math.min(100, progress)}%` }} />
        </div>

        <div className="project-card__meta">
          <span className="project-card__amount">{formatFCFACompact(project.amount_raised)}</span>
          <span className="text-muted">sur {formatFCFACompact(project.funding_goal)}</span>
        </div>

        {/* Date de mise en ligne : elle situe l'offre dans le temps, ce que le
            seul pourcentage de collecte ne dit pas. */}
        {project.published_at && (
          <p className="project-card__date">Publié le {formatDate(project.published_at)}</p>
        )}
      </div>
    </Link>
  )
}
