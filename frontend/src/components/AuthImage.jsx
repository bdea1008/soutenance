import { useEffect, useState } from 'react'
import { fetchProtectedImage } from '../api/reports'
import Icon from './Icon'

/**
 * Image servie par une route authentifiée (photos de chantier).
 * Gère le chargement, l'échec, et libère l'URL locale au démontage.
 */
export default function AuthImage({ url, alt, className = '', style, onClick }) {
  const [src, setSrc] = useState(null)
  const [failed, setFailed] = useState(false)

  useEffect(() => {
    let objectUrl = null
    let cancelled = false

    setSrc(null)
    setFailed(false)

    fetchProtectedImage(url)
      .then((created) => {
        if (cancelled) {
          URL.revokeObjectURL(created)
          return
        }
        objectUrl = created
        setSrc(created)
      })
      .catch(() => {
        if (!cancelled) setFailed(true)
      })

    return () => {
      cancelled = true
      if (objectUrl) URL.revokeObjectURL(objectUrl)
    }
  }, [url])

  if (failed) {
    return (
      <div className={`photo-fallback ${className}`} style={style}>
        <Icon name="image" size={22} title="Photo indisponible" />
      </div>
    )
  }

  if (!src) {
    return <div className={`photo-loading ${className}`} style={style} aria-label="Chargement de la photo" />
  }

  return <img src={src} alt={alt} className={className} style={style} onClick={onClick} />
}
