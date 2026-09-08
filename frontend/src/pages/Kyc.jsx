import { useCallback, useEffect, useState } from 'react'
import { Link, Navigate } from 'react-router-dom'
import client, { errorMessage } from '../api/client'
import DossierPanel from '../components/DossierPanel'
import { useAuth } from '../context/AuthContext'
import { staffHome } from '../utils/roles'
import BackLink from '../components/BackLink'

const KYC_TONE = {
  verified: 'badge--risk-low',
  pending: 'badge--risk-medium',
  rejected: 'badge--risk-high',
  none: '',
}

/**
 * Dossier de l'opérateur (niveau 2) : les pièces déposées une seule fois.
 *
 * La liste dépend du profil — investisseur, particulier ou promoteur
 * immobilier — et c'est le serveur qui la fixe : cette page se contente de
 * l'afficher. Les pièces propres à un projet sont ailleurs (ProjectDossier).
 */
export default function Kyc() {
  const { user, refreshUser } = useAuth()

  const [dossier, setDossier] = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')

  // Ni l'administrateur ni le rôle juridique ne sont soumis à une
  // vérification documentaire (§7.1) : leur checklist est vide et cette page
  // n'aurait rien à leur dire.
  const isStaff = user.role === 'admin' || user.role === 'legal'

  const load = useCallback(async () => {
    if (isStaff) return

    try {
      const res = await client.get('/me/documents')
      setDossier(res.data)
    } catch (err) {
      setError(errorMessage(err, 'Impossible de charger votre dossier.'))
    } finally {
      setLoading(false)
    }
  }, [isStaff])

  useEffect(() => { load() }, [load])

  async function reload() {
    await load()
    await refreshUser()
  }

  if (user.role === 'admin') return <Navigate to="/admin/documents" replace />
  if (user.role === 'legal') return <Navigate to={staffHome('legal')} replace />
  if (loading) return <div className="spinner" />
  if (!dossier) return <div className="container section"><div className="alert alert--error">{error}</div></div>

  const { kyc_status: kycStatus, kyc_status_label: kycLabel, profile_label: profileLabel, progress } = dossier
  const isPromoter = user.role === 'promoter'

  return (
    <div className="container">
      {/* On atteint cette page depuis plusieurs relances (tableau de bord,
          projets, fiche projet) : le retour suit l'historique plutôt que de
          nommer une origine qui serait fausse une fois sur deux. */}
      <BackLink />
      <div className="dash-head">
        <p className="eyebrow">Vérification d’identité</p>
        <div className="promo-head">
          <h1 style={{ margin: 0 }}>{isPromoter ? 'Mon dossier de promoteur' : 'Mon dossier KYC'}</h1>
          <span className={`badge ${KYC_TONE[kycStatus] ?? ''}`}>{kycLabel}</span>
        </div>
        <p className="text-muted">
          {isPromoter ? (
            <>
              Dossier <b>{profileLabel}</b> — ces pièces ne sont demandées qu’une fois : elles décrivent
              {profileLabel === 'Particulier' ? ' votre situation' : ' votre structure'}, pas un projet
              en particulier. Chaque projet a ensuite son propre dossier.
            </>
          ) : (
            <>La vérification est requise avant d’investir.</>
          )}{' '}
          Vos pièces ne sont consultables que par vous et par un administrateur.
        </p>

        <div className="dossier-bar" title={`${progress.satisfied} pièces validées sur ${progress.required}`}>
          <div
            className="dossier-bar__fill"
            style={{ width: `${progress.required ? (progress.satisfied / progress.required) * 100 : 0}%` }}
          />
        </div>
      </div>

      {kycStatus === 'verified' && (
        <div className="alert alert--success">
          Votre dossier est validé.{' '}
          {isPromoter
            ? 'Vous pouvez publier un projet dont le dossier d’opération est complet.'
            : 'Vous avez accès à l’ensemble des fonctionnalités.'}
        </div>
      )}
      {kycStatus === 'rejected' && (
        <div className="alert alert--error">
          Une pièce a été rejetée. Consultez le motif ci-dessous et déposez une nouvelle version.
        </div>
      )}

      <section className="section" style={{ paddingTop: '1rem' }}>
        <DossierPanel dossier={dossier} onChanged={reload} />

        <p className="text-muted" style={{ fontSize: '0.85rem', marginTop: '1.5rem' }}>
          {isPromoter ? (
            <>Le dossier propre à chaque projet se remplit depuis <Link to="/promoteur/projets">Mes projets</Link>.</>
          ) : (
            <>Besoin d’aide ? Revenez à votre <Link to="/tableau-de-bord">tableau de bord</Link>.</>
          )}
        </p>
      </section>
    </div>
  )
}
