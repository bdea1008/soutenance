import { useCallback, useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import client, { errorMessage } from '../../api/client'
import { useAuth } from '../../context/AuthContext'
import { formatFCFA } from '../../utils/format'
import PlanCard from '../../components/PlanCard'
import CheckoutDialog from '../../components/CheckoutDialog'
import Icon from '../../components/Icon'
import { AcceptedMethods, ProviderLogo } from '../../components/PaymentMethod'
import PaymentFields from '../../components/PaymentFields'
import useConfirm from '../../hooks/useConfirm'
import {
  emptyInstrument,
  instrumentLabel,
  instrumentPayload,
  PAYMENT_PROVIDERS,
} from '../../utils/payment'

/** Date ISO → « 23 août 2026 ». */
function formatDate(value) {
  if (!value) return '—'
  return new Date(value).toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' })
}

/**
 * Échéance après renouvellement.
 *
 * Reproduit la règle du serveur (`SubscriptionController::renew`) : on
 * prolonge depuis l'échéance en cours si elle est à venir, sinon depuis
 * aujourd'hui — sans quoi renouveler en avance ferait perdre les jours
 * restants. Le récapitulatif doit annoncer la date que le serveur posera,
 * pas une approximation.
 */
function renewedUntil(endsAt) {
  const from = endsAt && new Date(endsAt) > new Date() ? new Date(endsAt) : new Date()
  from.setMonth(from.getMonth() + 1)
  return from.toISOString()
}

export default function Subscription() {
  const { user, refreshUser } = useAuth()

  const [plans, setPlans] = useState([])
  const [providers, setProviders] = useState(PAYMENT_PROVIDERS)
  const [subscription, setSubscription] = useState(null)
  const [usage, setUsage] = useState({ active_projects: 0, max_active_projects: null })
  const [payments, setPayments] = useState([])

  const [instrument, setInstrument] = useState(() => emptyInstrument(user, PAYMENT_PROVIDERS))
  const [loading, setLoading] = useState(true)
  const [busy, setBusy] = useState('')
  const [error, setError] = useState('')
  const [notice, setNotice] = useState('')

  // Paiement en cours de composition : `{ kind: 'subscribe'|'renew', plan }`.
  // `null` = aucune fenêtre ouverte.
  const [checkout, setCheckout] = useState(null)
  const [checkoutError, setCheckoutError] = useState('')
  const { confirm, confirmDialog } = useConfirm()

  const load = useCallback(async () => {
    try {
      const [catalogue, current, history] = await Promise.all([
        client.get('/subscription-plans'),
        client.get('/me/subscription'),
        client.get('/me/payments'),
      ])
      // Le catalogue est public et porte les deux publics ; on n'affiche que
      // les paliers réellement souscriptibles par ce compte — le serveur
      // refuserait les autres (StoreSubscriptionRequest).
      setPlans(catalogue.data.plans.filter(
        (plan) => !plan.promoter_types || plan.promoter_types.includes(user.promoter_type),
      ))
      // L'API fait foi sur les fournisseurs supportés ; la liste locale n'est
      // qu'un repli tant qu'elle n'a pas répondu.
      if (catalogue.data.providers?.length) {
        setProviders(catalogue.data.providers)
      }
      setSubscription(current.data.subscription)
      setUsage(current.data.usage)
      setPayments(history.data.payments)
    } catch (err) {
      setError(errorMessage(err, 'Impossible de charger votre abonnement.'))
    } finally {
      setLoading(false)
    }
  }, [user.promoter_type])

  useEffect(() => { load() }, [load])

  /** Exécute une action d'abonnement puis resynchronise la page et le profil. */
  async function run(key, request, { confirmWith, inDialog = false } = {}) {
    if (confirmWith && !await confirm(confirmWith)) return false

    setBusy(key)
    setError('')
    setCheckoutError('')
    setNotice('')
    try {
      const res = await request()
      setNotice(res.data.message)
      await load()
      // `has_active_subscription` conditionne l'affichage ailleurs (navbar, projets).
      await refreshUser()
      return true
    } catch (err) {
      const message = errorMessage(err, 'Opération impossible.')
      // L'erreur doit s'afficher là où l'utilisateur regarde : dans la fenêtre
      // de paiement s'il y est, sinon en tête de page.
      if (inDialog) setCheckoutError(message)
      else setError(message)
      return false
    } finally {
      setBusy('')
    }
  }

  async function confirmCheckout(e) {
    e.preventDefault()

    const done = checkout.kind === 'renew'
      ? await run(
        'renew',
        () => client.post('/me/subscription/renew', instrumentPayload(instrument)),
        { inDialog: true },
      )
      : await run(
        `subscribe:${checkout.plan.tier}`,
        () => client.post('/subscriptions', {
          tier: checkout.plan.tier,
          ...instrumentPayload(instrument),
        }),
        { inDialog: true },
      )

    if (done) setCheckout(null)
  }

  const cancel = () => run(
    'cancel',
    () => client.post('/me/subscription/cancel'),
    {
      confirmWith: {
        tone: 'danger',
        icon: 'ban',
        eyebrow: 'Abonnement',
        title: 'Résilier votre abonnement ?',
        subtitle: subscription ? `${subscription.tier_label} · échéance ${formatDate(subscription.ends_at)}` : null,
        message: 'La résiliation est immédiate : vous ne pourrez plus publier de nouveau projet. '
          + 'Vos projets déjà en ligne, eux, restent visibles et continuent de collecter.',
        confirmLabel: 'Résilier',
        confirmTone: 'danger',
      },
    },
  )

  if (loading) return <div className="spinner" />

  const quota = usage.max_active_projects
  const quotaPercent = quota ? Math.min(100, (usage.active_projects / quota) * 100) : 0
  const submitting = busy.startsWith('subscribe') || busy === 'renew'

  return (
    <div className="container">
      <div className="dash-head">
        <p className="eyebrow">Espace promoteur</p>
        <div className="promo-head">
          <h1 style={{ margin: 0 }}>Mon abonnement</h1>
          <Link to="/promoteur/projets" className="btn btn--ghost">Mes projets</Link>
        </div>
      </div>

      {error && <div className="alert alert--error">{error}</div>}
      {notice && <div className="alert alert--success">{notice}</div>}

      <section className="section" style={{ paddingTop: '1rem' }}>
        {/* --- Abonnement en cours ------------------------------------------ */}
        {subscription ? (
          <div className="card sub-card">
            <div className="sub-card__grid">
              <div>
                <p className="sub-card__eyebrow">Abonnement en cours</p>
                <h2 className="sub-card__tier">{subscription.tier_label}</h2>
                <p className="sub-card__price">
                  {formatFCFA(subscription.price)} par mois · {subscription.status_label.toLowerCase()}
                </p>

                <div className="sub-card__meta">
                  <div className="sub-card__stat">
                    <b>{subscription.days_remaining} j</b>
                    <span>avant échéance</span>
                  </div>
                  <div className="sub-card__stat">
                    <b>{formatDate(subscription.ends_at)}</b>
                    <span>renouvellement</span>
                  </div>
                </div>

                <div className="sub-card__actions">
                  <button className="btn btn--accent" onClick={() => setCheckout({ kind: 'renew' })}>
                    Renouveler (+1 mois)
                  </button>
                  <button
                    className="btn sub-card__ghost"
                    disabled={busy === 'cancel'}
                    onClick={cancel}
                  >
                    {busy === 'cancel' ? 'Traitement…' : 'Résilier'}
                  </button>
                </div>
              </div>

              {/* Quota de publication : la contrepartie concrète du palier —
                  c'est ce qu'on achète, ça vaut d'être sur la carte. */}
              <div className="sub-card__stat">
                <p className="sub-card__eyebrow" style={{ marginBottom: '0.5rem' }}>Projets en ligne</p>
                <b style={{ fontSize: '1.6rem' }}>
                  {usage.active_projects} <span style={{ opacity: 0.6 }}>/ {quota ?? '∞'}</span>
                </b>
                {quota && (
                  <div className="sub-card__gauge">
                    <span style={{ width: `${quotaPercent}%` }} />
                  </div>
                )}
                <span style={{ display: 'block', marginTop: '0.5rem' }}>
                  {quota && usage.active_projects >= quota
                    ? 'Quota atteint : résiliez pour passer à un palier supérieur avant de publier.'
                    : 'inclus dans votre palier'}
                </span>
              </div>
            </div>
          </div>
        ) : (
          <div className="alert alert--info">
            Vous n’avez aucun abonnement actif. Un abonnement est requis pour <b>publier</b> vos projets ;
            vous pouvez préparer vos brouillons sans abonnement.
          </div>
        )}

        {/* --- Choix d'un palier -------------------------------------------- */}
        <h2 style={{ fontSize: '1.3rem', marginTop: '2.5rem' }}>
          {subscription ? 'Autres paliers' : 'Choisir un palier'}
        </h2>

        {subscription && (
          <p className="text-muted" style={{ marginTop: 0 }}>
            Pour changer de palier, résiliez votre abonnement en cours puis souscrivez au nouveau.
          </p>
        )}

        <div className="grid grid--3 plan-grid">
          {plans.map((plan) => {
            const isCurrent = subscription?.tier === plan.tier
            return (
              <PlanCard
                key={plan.tier}
                plan={plan}
                current={isCurrent}
                featured={!subscription && plan.tier === 'premium'}
                action={isCurrent ? null : (
                  <button
                    className="btn btn--primary btn--block"
                    disabled={Boolean(subscription)}
                    onClick={() => setCheckout({ kind: 'subscribe', plan })}
                    title={subscription ? 'Résiliez votre abonnement en cours pour changer de palier' : undefined}
                  >
                    Souscrire
                  </button>
                )}
              />
            )
          })}
        </div>

        <div style={{ display: 'flex', justifyContent: 'center', marginTop: '1.5rem' }}>
          <AcceptedMethods providers={providers} />
        </div>

        {/* --- Historique de facturation ------------------------------------ */}
        <h2 style={{ fontSize: '1.3rem', marginTop: '2.5rem' }}>Historique de facturation</h2>
        {payments.length === 0 ? (
          <p className="text-muted">Aucun paiement enregistré.</p>
        ) : (
          <div className="card">
            {payments.map((p) => (
              <div key={p.id} className="bill-row">
                {/* Le logo identifie le moyen de paiement plus vite que son
                    nom écrit, et aligne le journal sur le reste du parcours. */}
                <span className="bill-row__logo">
                  <ProviderLogo provider={p.provider} label={p.provider_label} height={22} />
                </span>
                <div className="bill-row__body">
                  <div className="bill-row__title">{p.purpose_label}</div>
                  <div className="bill-row__meta">
                    {formatDate(p.paid_at)} · réf. {p.reference}
                  </div>
                </div>
                <div className="bill-row__amount">
                  <b>{formatFCFA(p.amount)}</b>
                  <span className="badge">{p.status_label}</span>
                </div>
              </div>
            ))}
          </div>
        )}

        <p className="text-muted" style={{ fontSize: '0.85rem', marginTop: '1rem' }}>
          Les paiements sont <b>simulés</b> durant cette phase : aucun montant n’est réellement débité.
        </p>
      </section>

      {/* --- Fenêtre de paiement ------------------------------------------- */}
      {checkout && (
        <CheckoutDialog
          open
          eyebrow="Abonnement promoteur"
          title={checkout.kind === 'renew' ? 'Renouveler votre abonnement' : 'Souscrire à un palier'}
          subtitle={checkout.kind === 'renew' ? subscription?.tier_label : checkout.plan.label}
          onClose={() => { setCheckout(null); setCheckoutError('') }}
          onSubmit={confirmCheckout}
          submitting={submitting}
          error={checkoutError}
          submitLabel={
            checkout.kind === 'renew'
              ? `Payer ${formatFCFA(subscription?.price ?? 0)}`
              : `Payer ${formatFCFA(checkout.plan.monthly_price)}`
          }
          note="Abonnement mensuel, sans reconduction automatique : rien n’est prélevé tant que vous ne renouvelez pas vous-même. Paiement simulé pendant la phase de démonstration."
          summary={
            checkout.kind === 'renew'
              ? {
                heading: 'Votre renouvellement',
                lines: [
                  { label: 'Palier', value: subscription?.tier_label },
                  { label: 'Durée ajoutée', value: '1 mois' },
                  {
                    label: 'Nouvelle échéance',
                    hint: `au lieu du ${formatDate(subscription?.ends_at)}`,
                    value: formatDate(renewedUntil(subscription?.ends_at)),
                  },
                  { label: 'Moyen de paiement', value: instrumentLabel(instrument, providers) },
                ],
                total: { label: 'Total dû aujourd’hui', value: formatFCFA(subscription?.price ?? 0) },
                note: 'Le mois s’ajoute à votre échéance en cours : renouveler en avance ne fait perdre aucun jour.',
              }
              : {
                heading: 'Votre abonnement',
                lines: [
                  { label: 'Palier', value: checkout.plan.label },
                  {
                    label: 'Projets en ligne',
                    hint: 'quota inclus',
                    value: checkout.plan.max_active_projects ?? 'Illimité',
                  },
                  { label: 'Durée', value: '1 mois' },
                  { label: 'Prix mensuel', value: formatFCFA(checkout.plan.monthly_price) },
                  { label: 'Moyen de paiement', value: instrumentLabel(instrument, providers) },
                ],
                total: { label: 'Total dû aujourd’hui', value: formatFCFA(checkout.plan.monthly_price) },
                note: 'L’abonnement débloque la publication de vos projets. Vos brouillons restent modifiables sans abonnement.',
              }
          }
        >
          <div className="pay-step">
            <div className="pay-step__head">
              <span className="pay-step__num">1</span>
              <h3 className="pay-step__title">Moyen de paiement</h3>
            </div>
            <PaymentFields
              providers={providers}
              value={instrument}
              onChange={setInstrument}
              disabled={submitting}
            />
          </div>

          <p className="invest-secure" style={{ marginTop: '1.25rem' }}>
            <Icon name="shield" size={15} /> Paiement sécurisé — résiliable à tout moment
          </p>
        </CheckoutDialog>
      )}

      {confirmDialog}
    </div>
  )
}
