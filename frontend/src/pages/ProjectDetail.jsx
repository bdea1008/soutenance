import { useEffect, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import client, { errorMessage } from '../api/client'
import { useAuth } from '../context/AuthContext'
import { formatDate, formatFCFA, formatFCFACompact, formatPercent } from '../utils/format'
import Icon from '../components/Icon'
import RiskBadge from '../components/RiskBadge'
import SiteReportTimeline from '../components/SiteReportTimeline'
import ProjectReviews from '../components/ProjectReviews'
import CheckoutDialog from '../components/CheckoutDialog'
import { AcceptedMethods } from '../components/PaymentMethod'
import PaymentFields from '../components/PaymentFields'
import {
  emptyInstrument,
  instrumentLabel,
  instrumentPayload,
  PAYMENT_PROVIDERS,
} from '../utils/payment'
import { fetchReports } from '../api/reports'
import { attemptPublish } from '../utils/publish'
import BackLink from '../components/BackLink'
import useConfirm from '../hooks/useConfirm'

/**
 * Montants proposés d'un clic : le ticket minimum et ses multiples courants,
 * plus le solde exact quand il ne reste presque rien à financer — c'est le
 * seul montant que l'API acceptera alors, autant le donner.
 *
 * Tout ce qui dépasse le reste à financer est écarté ici : le serveur le
 * refuse (422), proposer un bouton qui échoue à coup sûr n'a pas de sens.
 */
function amountPresets(min, remaining) {
  const values = [min, min * 2, min * 5].filter((v) => v > 0 && v <= remaining)

  // Le solde n'est proposé que lorsqu'il tombe sous les multiples habituels :
  // c'est alors le dernier montant que l'API accepte, et souvent celui qui
  // boucle la collecte. Au-dessus, « tout le reste » se chiffrerait en dizaines
  // de millions — ce n'est pas une proposition qu'on met derrière un bouton.
  if (remaining > 0 && remaining < min * 5 && !values.includes(remaining)) {
    values.push(remaining)
  }

  return [...new Set(values)]
    .sort((a, b) => a - b)
    .map((value) => ({
      value,
      label: value === remaining && value !== min ? 'Solde restant' : formatFCFACompact(value),
    }))
}

function InvestPanel({ project, onInvested }) {
  const { user } = useAuth()
  const f = project.financials

  const remaining = Math.max(0, f.funding_goal - f.amount_raised)
  const minimum = f.min_investment || 1

  const [open, setOpen] = useState(false)
  const [amount, setAmount] = useState(minimum)
  const [instrument, setInstrument] = useState(() => emptyInstrument(user, PAYMENT_PROVIDERS))
  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState('')
  const [success, setSuccess] = useState('')

  const canInvest = user.role === 'investor' && user.kyc_verified
  const isOpen = project.status === 'published'

  async function submit(e) {
    e.preventDefault()
    setSubmitting(true)
    setError('')
    try {
      const res = await client.post(`/projects/${project.id}/invest`, {
        amount: Number(amount),
        ...instrumentPayload(instrument),
      })
      // La confirmation se lit sur la fiche, pas dans une fenêtre qu'on vient
      // de fermer : c'est la fiche qui porte le nouveau montant collecté.
      setOpen(false)
      setSuccess(res.data.message)
      onInvested()
    } catch (err) {
      setError(errorMessage(err, 'Investissement impossible.'))
    } finally {
      setSubmitting(false)
    }
  }

  // Un administrateur consulte cette fiche pour superviser, jamais pour
  // investir : ni encart d'investissement, ni relance KYC (il n'y est pas
  // soumis). On le renvoie vers l'outil qui correspond à son rôle.
  if (user.role === 'admin') {
    return (
      <div className="alert alert--info">
        Vous consultez ce projet en tant qu’administrateur.
        {' '}<Link to="/admin/projets">Superviser les projets →</Link>
      </div>
    )
  }

  // Même logique pour le rôle juridique : une lecture de vérification, jamais
  // un placement.
  if (user.role === 'legal') {
    return (
      <div className="alert alert--info">
        Vous consultez ce projet au titre de la vérification juridique.
        {' '}<Link to="/verification-legale">Voir tous les projets →</Link>
      </div>
    )
  }

  if (!isOpen) {
    return <div className="alert alert--info">Ce projet n’est pas ouvert au co-investissement.</div>
  }

  if (user.role === 'promoter') {
    return <div className="alert alert--info">Les comptes promoteurs ne peuvent pas investir.</div>
  }

  if (!canInvest) {
    return (
      <div className="alert alert--info">
        La vérification KYC est requise avant d’investir. Votre statut : <b>{user.kyc_status}</b>.
        {' '}<Link to="/verification">Déposer mes pièces →</Link>
      </div>
    )
  }

  if (remaining === 0) {
    return (
      <div className="alert alert--success">
        Objectif de collecte atteint. Ce projet n’accepte plus de nouveaux investissements.
      </div>
    )
  }

  const numericAmount = Number(amount) || 0
  const share = f.funding_goal > 0 ? (numericAmount / f.funding_goal) * 100 : 0
  const presets = amountPresets(minimum, remaining)

  return (
    <>
      {success && <div className="alert alert--success">{success}</div>}

      <button className="btn btn--primary btn--block" onClick={() => { setSuccess(''); setOpen(true) }}>
        Investir dans ce projet
      </button>

      <div className="invest-box__foot">
        <span className="invest-secure">
          <Icon name="shield" size={15} /> Paiement sécurisé
        </span>
        <AcceptedMethods providers={PAYMENT_PROVIDERS} label="" />
      </div>

      <CheckoutDialog
        open={open}
        eyebrow="Co-investissement"
        title="Investir dans ce projet"
        subtitle={project.title}
        onClose={() => setOpen(false)}
        onSubmit={submit}
        submitting={submitting}
        error={error}
        submitLabel={`Investir ${formatFCFA(numericAmount)}`}
        note="En confirmant, vous acceptez les conditions du projet. Le paiement est simulé pendant la phase de démonstration : aucun montant n’est réellement débité."
        summary={{
          heading: 'Votre investissement',
          lines: [
            { label: 'Projet', value: project.title },
            { label: 'Montant investi', value: formatFCFA(numericAmount) },
            {
              label: 'Part du projet',
              hint: `sur ${formatFCFACompact(f.funding_goal)} de collecte`,
              value: `${share.toFixed(2).replace('.', ',')} %`,
            },
            {
              label: 'Frais de plateforme',
              hint: 'investir est sans frais',
              value: '0 FCFA',
              strong: true,
            },
            // Le moyen retenu se relit ici, à côté du montant : c'est le
            // dernier point à vérifier avant de confirmer.
            { label: 'Moyen de paiement', value: instrumentLabel(instrument, PAYMENT_PROVIDERS) },
          ],
          total: { label: 'Total dû aujourd’hui', value: formatFCFA(numericAmount) },
          note: (
            <>
              Rendement annoncé par le promoteur : <b>{formatPercent(f.expected_return_rate)}</b>
              {f.duration_months ? ` sur ${f.duration_months} mois` : ''}. C’est un objectif
              contractuel, pas une garantie — le capital investi reste exposé au risque du projet.
            </>
          ),
        }}
      >
        <div className="pay-step">
          <div className="pay-step__head">
            <span className="pay-step__num">1</span>
            <h3 className="pay-step__title">Montant à investir</h3>
          </div>

          <div className="field" style={{ margin: 0 }}>
            <label htmlFor="amount">Montant en FCFA</label>
            <input
              id="amount"
              type="number"
              min={minimum}
              max={remaining}
              step={1}
              value={amount}
              onChange={(e) => setAmount(e.target.value)}
              required
              autoFocus
            />
            <small className="text-muted">
              Ticket minimum {formatFCFA(minimum)} · reste à financer {formatFCFA(remaining)}
            </small>

            <div className="amount-presets">
              {presets.map((preset) => (
                <button
                  key={preset.value}
                  type="button"
                  className={`amount-preset${numericAmount === preset.value ? ' amount-preset--on' : ''}`}
                  onClick={() => setAmount(preset.value)}
                >
                  {preset.label}
                </button>
              ))}
            </div>
          </div>
        </div>

        <div className="pay-step">
          <div className="pay-step__head">
            <span className="pay-step__num">2</span>
            <h3 className="pay-step__title">Moyen de paiement</h3>
          </div>
          <PaymentFields
            providers={PAYMENT_PROVIDERS}
            value={instrument}
            onChange={setInstrument}
            disabled={submitting}
          />
        </div>
      </CheckoutDialog>
    </>
  )
}

/**
 * Encart du porteur, à la place de l'encart d'investissement : sur son propre
 * projet, un promoteur n'a pas à lire « les comptes promoteurs ne peuvent pas
 * investir ». Il y trouve ce qu'il peut faire, dont publier — le bouton est
 * proposé sur tout brouillon, complet ou non, c'est en le pressant qu'on
 * apprend ce qui manque encore.
 */
function OwnerPanel({ project, onPublished }) {
  const navigate = useNavigate()

  const [dossier, setDossier] = useState(null)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')
  const [notice, setNotice] = useState('')
  const { confirm, confirmDialog } = useConfirm()

  useEffect(() => {
    client
      .get(`/projects/${project.id}/dossier`)
      .then((res) => setDossier(res.data))
      .catch(() => setDossier(null))
  }, [project.id])

  const publishable = ['draft', 'pending_review'].includes(project.status)
  const reportable = ['funded', 'in_progress', 'completed'].includes(project.status)

  // Un projet déjà en ligne ne se publie pas une seconde fois (le serveur
  // répondrait 409). L'absence du bouton doit se lire, pas se deviner : on dit
  // pourquoi il n'y est pas plutôt que de laisser un vide.
  const stateNote = {
    published: 'Ce projet est en ligne et ouvert au co-investissement. Il n’y a plus rien à publier.',
    funded: 'Objectif de collecte atteint : le projet est financé et n’accepte plus d’investissement.',
    in_progress: 'Le chantier est en cours. Tenez vos investisseurs informés par des rapports d’avancement.',
    completed: 'Projet livré. Il reste visible dans le catalogue comme référence.',
    cancelled: 'Ce projet a été retiré du catalogue par l’administration. Contactez-la pour le rétablir.',
  }[project.status]

  async function publish() {
    setBusy(true)
    setError('')
    setNotice('')

    const { ok, message, blocker: refusal } = await attemptPublish(project)

    if (ok) {
      setNotice(message)
      onPublished()
    } else if (refusal) {
      if (await confirm({ ...refusal, arrow: true, cancelLabel: 'Plus tard' })) {
        navigate(refusal.to)
      }
    } else {
      setError(message)
    }

    setBusy(false)
  }

  return (
    <>
      {error && <div className="alert alert--error">{error}</div>}
      {notice && <div className="alert alert--success">{notice}</div>}

      {confirmDialog}

      {dossier && (
        <div className="data-row">
          <span>Dossier de financement</span>
          <b>{dossier.progress.satisfied}/{dossier.progress.required} pièces</b>
        </div>
      )}

      {stateNote && <div className="alert alert--info" style={{ marginTop: '1rem' }}>{stateNote}</div>}

      <div className="stack" style={{ gap: '0.6rem', marginTop: '1.2rem' }}>
        {publishable && (
          <button className="btn btn--primary btn--block" disabled={busy} onClick={publish}>
            {busy ? 'Publication…' : 'Publier ce projet'}
          </button>
        )}
        {reportable && (
          <Link to={`/promoteur/projets/${project.id}/rapports`} className="btn btn--ghost btn--block">
            Rapports de chantier
          </Link>
        )}
        <Link to={`/promoteur/projets/${project.id}/dossier`} className="btn btn--ghost btn--block">
          {dossier?.complete ? 'Voir le dossier' : 'Compléter le dossier'}
        </Link>
        <Link to={`/promoteur/projets/${project.id}/modifier`} className="btn btn--ghost btn--block">
          Modifier le projet
        </Link>
      </div>
    </>
  )
}

export default function ProjectDetail() {
  const { id } = useParams()
  const { user } = useAuth()
  const [project, setProject] = useState(null)
  const [reports, setReports] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')

  function load() {
    client
      .get(`/projects/${id}`)
      .then((res) => setProject(res.data.project))
      .catch((err) => setError(errorMessage(err, 'Projet introuvable.')))
      .finally(() => setLoading(false))
  }

  useEffect(() => { load() }, [id])

  // Journal de chantier : chargé à part, son absence ne doit pas empêcher
  // l'affichage du projet.
  useEffect(() => {
    fetchReports(id)
      .then((data) => setReports(data.data))
      .catch(() => setReports([]))
  }, [id])

  if (loading) return <div className="spinner" />
  if (error) return <div className="container section"><div className="alert alert--error">{error}</div></div>

  const f = project.financials
  const ai = project.ai_score
  // Le porteur du projet ne voit pas la fiche comme un investisseur : c'est son
  // brouillon, pas une offre qu'on lui fait.
  const isOwner = user?.role === 'promoter' && user.id === project.promoter?.id

  return (
    <>
      <div className="detail-hero">
        <div className="container">
          {/* Fond clair de l'en-tête : le retour y garde son aspect habituel.
              Destination nommée plutôt qu'historique — c'est presque toujours
              du catalogue qu'on arrive, et le porteur a son propre chemin. */}
          <BackLink to={isOwner ? '/promoteur/projets' : '/projets'} label={isOwner ? 'Mes projets' : 'Tous les projets'} />
          <div style={{ display: 'flex', gap: '0.5rem', flexWrap: 'wrap', marginBottom: '0.75rem' }}>
            <span className="badge">{project.status_label}</span>
            {project.category && <span className="badge" style={{ textTransform: 'capitalize' }}>{project.category}</span>}
            {ai && <RiskBadge level={ai.risk_level} />}
          </div>
          <h1 style={{ marginBottom: '0.25rem' }}>{project.title}</h1>
          <p className="text-muted">
            <Icon name="pin" size={15} /> {project.location.city}{project.location.region ? `, ${project.location.region}` : ''}
            {project.published_at && <> · Publié le {formatDate(project.published_at)}</>}
          </p>
        </div>
      </div>

      <div className="container section">
        <div className="detail-grid">
          {/* Colonne principale */}
          <div className="stack">
            <div className="progress" style={{ height: 12 }}>
              <span style={{ width: `${Math.min(100, f.funding_progress)}%` }} />
            </div>
            <p>
              <b className="stat__value" style={{ fontSize: '1.3rem' }}>{formatFCFACompact(f.amount_raised)}</b>{' '}
              <span className="text-muted">collectés sur {formatFCFACompact(f.funding_goal)} ({f.funding_progress}%)</span>
            </p>

            <h3>Description</h3>
            <p className="text-muted">{project.description || project.summary}</p>

            {ai && (
              <div className="card" style={{ padding: '1.2rem' }}>
                <h3 style={{ marginTop: 0 }} className="head-icon"><Icon name="spark" /> Analyse IA</h3>
                <div className="data-row"><span>Score de confiance</span><b>{ai.confidence_score}/100</b></div>
                <div className="data-row"><span>Rentabilité estimée</span><b>{formatPercent(ai.roi_estimate)}</b></div>
                <div className="data-row"><span>Délai de rentabilité</span><b>{ai.payback_months ? `${ai.payback_months} mois` : '—'}</b></div>
                <div className="data-row"><span>Niveau de risque</span><RiskBadge level={ai.risk_level} /></div>

                {/* Facteurs explicatifs : un score sans justification n'aide
                    personne à décider. */}
                {Array.isArray(ai.factors) && ai.factors.length > 0 && (
                  <ul className="factors">
                    {ai.factors.map((factor, i) => (
                      <li key={i} className={`factor factor--${factor.impact ?? 'neutre'}`}>
                        <span className="factor__mark" aria-hidden="true">
                          {factor.impact === 'positif' ? '+' : factor.impact === 'négatif' ? '−' : '·'}
                        </span>
                        <span>
                          <b>{factor.label}</b> — {factor.detail}
                        </span>
                      </li>
                    ))}
                  </ul>
                )}

                <p className="text-muted" style={{ fontSize: '0.78rem', marginBottom: 0, marginTop: '1rem' }}>
                  Estimation produite par le modèle {ai.model_version || 'de scoring'} à partir des
                  caractéristiques du projet et de l’historique du promoteur. Elle ne constitue pas
                  une garantie de rendement.
                </p>
              </div>
            )}

            {project.promoter && (
              <p className="text-muted">
                Promoteur : <b>{project.promoter.name}</b>{project.promoter.kyc_verified && <span className="verified"><Icon name="check" size={15} /> vérifié</span>}
              </p>
            )}

            {/* Suivi de chantier (§7.5) — n'apparaît qu'une fois les travaux lancés. */}
            {(reports.length > 0 || project.construction) && (
              <section style={{ marginTop: '1rem' }}>
                <div className="promo-head">
                  <h3 style={{ margin: 0 }} className="head-icon"><Icon name="building" /> Suivi du chantier</h3>
                  {project.construction && (
                    <span className="text-muted">
                      {project.construction.progress_percentage} % réalisés ·{' '}
                      {reports.length} rapport{reports.length > 1 ? 's' : ''}
                    </span>
                  )}
                </div>
                {project.construction && (
                  <div className="progress" style={{ height: 10, margin: '0.9rem 0 1.5rem' }}>
                    <span style={{ width: `${Math.min(100, project.construction.progress_percentage)}%` }} />
                  </div>
                )}
                <SiteReportTimeline reports={reports} />
              </section>
            )}

            {/* Avis des investisseurs (§2, extension d'« Investir dans un
                projet ») — accessible en lecture à quiconque voit la fiche ;
                le formulaire ne s'affiche qu'aux investisseurs vérifiés,
                l'API restant seule juge de qui a réellement investi. */}
            <ProjectReviews
              projectId={project.id}
              canReview={user.role === 'investor' && user.kyc_verified}
            />
          </div>

          {/* Encart investissement — « conditions » pour qui n'investit pas. */}
          <aside className="card invest-box">
            <h3 style={{ marginTop: 0 }}>
              {isOwner
                ? 'Votre projet'
                : ['admin', 'legal'].includes(user?.role) ? 'Conditions du projet' : 'Investir dans ce projet'}
            </h3>

            {/* Le rendement annoncé décide de la suite : il passe devant le
                reste, en un seul chiffre, plutôt que noyé dans la liste. */}
            <div className="invest-figure">
              <b className="invest-figure__value">{formatPercent(f.expected_return_rate)}</b>
              <span className="invest-figure__label">
                de rendement annoncé{f.duration_months ? ` sur ${f.duration_months} mois` : ''}
              </span>
            </div>

            <div className="data-row"><span>Ticket minimum</span><b>{formatFCFACompact(f.min_investment)}</b></div>
            <div className="data-row"><span>Durée du placement</span><b>{f.duration_months ? `${f.duration_months} mois` : '—'}</b></div>
            <div className="data-row">
              <span>Reste à financer</span>
              <b>{formatFCFACompact(Math.max(0, f.funding_goal - f.amount_raised))}</b>
            </div>

            <div style={{ marginTop: '1.2rem' }}>
              {isOwner
                ? <OwnerPanel project={project} onPublished={load} />
                : <InvestPanel project={project} onInvested={load} />}
            </div>
          </aside>
        </div>
      </div>
    </>
  )
}
