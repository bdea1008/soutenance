import { useState } from 'react'
import AuthImage from './AuthImage'

function formatDate(value) {
  if (!value) return '—'
  return new Date(value).toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' })
}

/**
 * Journal d'avancement d'un chantier (§7.5), du plus récent au plus ancien.
 * `onEdit` / `onDelete` ne sont fournis que dans l'espace promoteur : côté
 * investisseur, le journal est en lecture seule.
 */
export default function SiteReportTimeline({ reports, onEdit, onDelete, busyId }) {
  const [zoom, setZoom] = useState(null)

  if (reports.length === 0) {
    return (
      <div className="card" style={{ padding: '2rem', textAlign: 'center' }}>
        <p className="text-muted" style={{ margin: 0 }}>
          Aucun rapport pour le moment. Le suivi démarre une fois le chantier lancé.
        </p>
      </div>
    )
  }

  return (
    <>
      <ol className="timeline">
        {reports.map((report) => (
          <li key={report.id} className="timeline__item">
            <span className="timeline__dot" aria-hidden="true" />

            <div className="card timeline__card">
              <div className="timeline__head">
                <div>
                  <b>{report.title}</b>
                  <div className="text-muted" style={{ fontSize: '0.85rem' }}>
                    {formatDate(report.reported_at)}
                    {report.author && ` · ${report.author.name}`}
                  </div>
                </div>
                <span className="badge badge--risk-low">{report.progress_percentage} %</span>
              </div>

              <div className="progress" style={{ height: 8, margin: '0.9rem 0' }}>
                <span style={{ width: `${Math.min(100, report.progress_percentage)}%` }} />
              </div>

              {report.description && (
                <p className="text-muted" style={{ whiteSpace: 'pre-line', margin: 0 }}>
                  {report.description}
                </p>
              )}

              {report.photos.length > 0 && (
                <div className="timeline__photos">
                  {report.photos.map((photo) => (
                    <AuthImage
                      key={photo.index}
                      url={photo.url}
                      alt={photo.original_name || `Photo ${photo.index + 1} du chantier`}
                      className="timeline__photo"
                      onClick={() => setZoom(photo)}
                    />
                  ))}
                </div>
              )}

              {(onEdit || onDelete) && (
                <div className="promo-item__actions" style={{ marginTop: '1rem' }}>
                  {onEdit && (
                    <button className="btn btn--ghost" onClick={() => onEdit(report)}>
                      Modifier
                    </button>
                  )}
                  {onDelete && (
                    <button
                      className="btn btn--ghost promo-item__danger"
                      disabled={busyId === report.id}
                      onClick={() => onDelete(report)}
                    >
                      Retirer
                    </button>
                  )}
                </div>
              )}
            </div>
          </li>
        ))}
      </ol>

      {zoom && (
        <div className="lightbox" role="dialog" aria-modal="true" onClick={() => setZoom(null)}>
          <button className="lightbox__close" aria-label="Fermer">×</button>
          <AuthImage url={zoom.url} alt={zoom.original_name || 'Photo du chantier'} className="lightbox__img" />
        </div>
      )}
    </>
  )
}
