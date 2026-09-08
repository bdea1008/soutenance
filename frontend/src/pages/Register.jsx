import { useMemo, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'
import { errorMessage } from '../api/client'
import Icon from '../components/Icon'
import BackButton from '../components/BackButton'
import logoMark from '../assets/logo-mark.png'

/**
 * Inscription par écrans successifs.
 *
 * Le parcours n'a pas la même longueur pour tout le monde : un investisseur en
 * a trois, un particulier qui cherche un financement aussi, un promoteur
 * immobilier en a un de plus pour déclarer sa structure. Les étapes sont donc
 * calculées à partir des choix déjà faits (`stepKeys`) plutôt que numérotées en
 * dur — ajouter un profil ne demandera qu'une ligne.
 *
 * Chaque étape est un `<form>` : « Continuer » est un envoi, donc la
 * validation native du navigateur s'applique champ par champ avant de passer à
 * la suivante, sans code de validation à réécrire.
 *
 * Ce qui est demandé ici est volontairement mince — le dossier de pièces vient
 * plus tard, quand le promoteur veut réellement mettre un projet en
 * financement (voir /verification et le dossier de chaque projet).
 */

const ROLES = [
  {
    value: 'investor',
    icon: 'wallet',
    label: 'Investisseur',
    pitch: 'Je place de l’argent dans des projets immobiliers.',
    points: [
      'Investir dès de petits montants, sans aucun frais',
      'Suivre l’avancement des chantiers financés',
      'Pièce d’identité et justificatif de domicile pour investir',
    ],
  },
  {
    value: 'promoter',
    icon: 'building',
    label: 'Porteur de projet',
    pitch: 'Je cherche un financement pour un projet immobilier.',
    points: [
      'Publier son projet et collecter des fonds',
      'Publier un rapport de chantier à chaque étape',
      'Abonnement mensuel requis pour mettre un projet en ligne',
    ],
  },
]

/** Sous-types de promoteur — ils ne montent pas le même dossier (§7.2). */
const PROMOTER_TYPES = [
  {
    value: 'individual',
    icon: 'users',
    label: 'Particulier',
    pitch: 'Je finance mon propre bien : construction, rénovation ou acquisition.',
    points: [
      'Un projet à la fois, le vôtre',
      'Dossier fondé sur vos revenus et votre apport',
      'Abonnement Particulier à tarif réduit',
    ],
  },
  {
    value: 'company',
    icon: 'building',
    label: 'Promoteur immobilier',
    pitch: 'Je suis une société qui monte des opérations immobilières.',
    points: [
      'Jusqu’à plusieurs projets en ligne simultanément',
      'Dossier fondé sur vos comptes et vos références',
      'Paliers Essentiel, Premium ou Entreprise',
    ],
  },
]

const EMPTY = {
  first_name: '', last_name: '', email: '', phone: '',
  role: '', promoter_type: '',
  company_name: '', legal_form: '', registration_number: '', tax_number: '', signatory_role: '',
  password: '', password_confirmation: '',
}

/**
 * Définition d'une étape : sa puce (courte, la place est comptée dans le fil)
 * et son titre. Les deux diffèrent volontairement — « Structure » ne fait pas
 * un titre de page, « Votre structure » ne tient pas dans une puce à 460 px.
 */
const STEP_DEFS = {
  role: { dot: 'Profil', title: 'Créer un compte', lead: 'Que venez-vous faire sur AndTabbax ?' },
  promoter_type: { dot: 'Type', title: 'Votre profil', lead: 'Quel type de porteur de projet êtes-vous ?' },
  company: { dot: 'Structure', title: 'Votre structure', lead: 'L’identité de la société qui portera vos opérations.' },
  contact: { dot: 'Contact', title: 'Vos coordonnées', lead: null },
  password: { dot: 'Sécurité', title: 'Votre mot de passe', lead: 'Dernière étape : sécurisez votre compte.' },
}

/** Étape portant chaque champ : sert à revenir sur le bon écran après un 422. */
const FIELD_STEP = {
  role: 'role',
  promoter_type: 'promoter_type',
  company_name: 'company',
  legal_form: 'company',
  registration_number: 'company',
  tax_number: 'company',
  signatory_role: 'company',
  first_name: 'contact', last_name: 'contact', email: 'contact', phone: 'contact',
  password: 'password', password_confirmation: 'password',
}

export default function Register() {
  const { register } = useAuth()
  const navigate = useNavigate()

  const [step, setStep] = useState(0)
  const [form, setForm] = useState(EMPTY)
  const [error, setError] = useState('')
  const [loading, setLoading] = useState(false)

  // Les écrans réellement traversés, déduits des choix déjà faits.
  const stepKeys = useMemo(() => {
    const keys = ['role']
    if (form.role === 'promoter') keys.push('promoter_type')
    if (form.role === 'promoter' && form.promoter_type === 'company') keys.push('company')
    return [...keys, 'contact', 'password']
  }, [form.role, form.promoter_type])

  const key = stepKeys[step]
  const def = STEP_DEFS[key]

  function update(e) {
    setForm({ ...form, [e.target.name]: e.target.value })
  }

  /** Choix sur carte : enregistre puis avance, sans bouton « Continuer ». */
  function choose(field, value) {
    setForm((current) => ({ ...current, [field]: value }))
    setError('')
    setStep((current) => current + 1)
  }

  function back() {
    setError('')
    setStep((current) => Math.max(0, current - 1))
  }

  async function handleSubmit(e) {
    e.preventDefault()
    setError('')
    setLoading(true)
    try {
      await register(form)
      navigate('/tableau-de-bord', { replace: true })
    } catch (err) {
      setError(errorMessage(err, 'Inscription impossible.'))

      // Un email déjà pris se refuse au dernier écran alors que le champ est
      // deux écrans plus haut : on ramène l'utilisateur là où il peut corriger.
      const fields = Object.keys(err.response?.data?.errors ?? {})
      const target = Math.min(
        ...fields.map((f) => {
          const index = stepKeys.indexOf(FIELD_STEP[f])
          return index === -1 ? step : index
        }),
        step,
      )

      if (target < step) {
        setStep(target)
      }
    } finally {
      setLoading(false)
    }
  }

  const chosenRole = ROLES.find((r) => r.value === form.role)
  const chosenType = PROMOTER_TYPES.find((t) => t.value === form.promoter_type)

  /** Grille de cartes, partagée par les deux écrans de choix. */
  function cards(options, field) {
    return (
      <div className="role-grid">
        {options.map((option) => (
          <button
            key={option.value}
            type="button"
            className={`role-card ${form[field] === option.value ? 'role-card--on' : ''}`}
            onClick={() => choose(field, option.value)}
          >
            <span className="role-card__head">
              <span className="role-card__icon"><Icon name={option.icon} size={24} /></span>
              <span className="role-card__label">{option.label}</span>
            </span>
            <span className="role-card__pitch">{option.pitch}</span>
            {/* Des <span> et non une <ul> : un bouton ne peut contenir que
                du contenu de phrasé, une liste y serait du HTML invalide. */}
            <span className="role-card__points">
              {option.points.map((point) => <span key={point}>{point}</span>)}
            </span>
            <span className="role-card__cta">Continuer →</span>
          </button>
        ))}
      </div>
    )
  }

  const isCardStep = key === 'role' || key === 'promoter_type'

  return (
    <div className={`auth-wrap ${isCardStep ? 'auth-wrap--wide' : ''}`}>
      {/* Unique retour de la page, à chaque étape : il recule d'une étape tant
          qu'il en reste une derrière, et quitte l'inscription à la première.
          Un seul sens, un seul endroit — d'où l'absence de second bouton
          « Retour » au bas des formulaires. */}
      <BackButton onBack={step > 0 ? back : null} />

      <div className="card auth-card">
        {/* Verrouillage compact plutôt que le logo complet : celui-ci fait
            ~220 px de haut et l'écran doit tenir sans défilement. */}
        <div className="brand-inline">
          <img src={logoMark} alt="" />
          <span>AndTabbax</span>
        </div>

        <div className="steps" aria-label={`Étape ${step + 1} sur ${stepKeys.length}`}>
          {stepKeys.map((stepKey, index) => (
            <span
              key={stepKey}
              className={`steps__dot ${index === step ? 'steps__dot--on' : ''} ${index < step ? 'steps__dot--done' : ''}`}
            >
              {STEP_DEFS[stepKey].dot}
            </span>
          ))}
        </div>

        <h1 style={{ fontSize: '1.6rem', marginBottom: '0.25rem' }}>{def.title}</h1>
        <p className="text-muted" style={{ marginBottom: '1rem' }}>
          {def.lead ?? `Inscription en tant que ${(chosenType ?? chosenRole)?.label.toLowerCase()}.`}
        </p>

        {error && <div className="alert alert--error">{error}</div>}

        {/* --- Le rôle, sur son propre écran --- */}
        {key === 'role' && cards(ROLES, 'role')}

        {/* --- Le sous-type de porteur de projet --- */}
        {key === 'promoter_type' && cards(PROMOTER_TYPES, 'promoter_type')}

        {/* --- La structure porteuse (promoteur immobilier seulement) --- */}
        {key === 'company' && (
          <form onSubmit={(e) => { e.preventDefault(); setStep(step + 1) }}>
            <div className="field">
              <label htmlFor="company_name">Raison sociale</label>
              <input id="company_name" name="company_name" value={form.company_name} onChange={update} required autoComplete="organization" />
            </div>

            <div className="form-row form-row--2">
              <div className="field">
                <label htmlFor="legal_form">Forme juridique</label>
                <input id="legal_form" name="legal_form" value={form.legal_form} onChange={update} required placeholder="SARL, SA, SUARL, SCI…" />
              </div>
              <div className="field">
                <label htmlFor="signatory_role">Votre fonction</label>
                <input id="signatory_role" name="signatory_role" value={form.signatory_role} onChange={update} required placeholder="Gérant, Directeur général…" />
              </div>
            </div>

            <div className="field">
              <label htmlFor="registration_number">Numéro RCCM</label>
              <input id="registration_number" name="registration_number" value={form.registration_number} onChange={update} required placeholder="SN-DKR-2024-B-1234" />
            </div>

            <div className="field">
              <label htmlFor="tax_number">NINEA</label>
              <input id="tax_number" name="tax_number" value={form.tax_number} onChange={update} required />
              <small className="text-muted">
                Les pièces justificatives (RCCM, statuts, bilans) seront demandées plus tard,
                avant votre première publication.
              </small>
            </div>

            <div className="auth-nav">
              <button className="btn btn--primary" type="submit">Continuer</button>
            </div>
          </form>
        )}

        {/* --- Identité et contact --- */}
        {key === 'contact' && (
          <form onSubmit={(e) => { e.preventDefault(); setStep(step + 1) }}>
            <div className="form-row form-row--2">
              <div className="field">
                <label htmlFor="first_name">Prénom</label>
                <input id="first_name" name="first_name" value={form.first_name} onChange={update} required autoComplete="given-name" />
              </div>
              <div className="field">
                <label htmlFor="last_name">Nom</label>
                <input id="last_name" name="last_name" value={form.last_name} onChange={update} required autoComplete="family-name" />
              </div>
            </div>

            <div className="field">
              <label htmlFor="email">Email</label>
              <input id="email" name="email" type="email" value={form.email} onChange={update} required autoComplete="email" />
            </div>

            <div className="field">
              <label htmlFor="phone">Téléphone</label>
              <input
                id="phone" name="phone" type="tel" value={form.phone} onChange={update}
                required autoComplete="tel" placeholder="+221 77 123 45 67"
              />
              <small className="text-muted">Depuis l’étranger, avec l’indicatif du pays.</small>
            </div>

            <div className="auth-nav">
              <button className="btn btn--primary" type="submit">Continuer</button>
            </div>
          </form>
        )}

        {/* --- Mot de passe et création --- */}
        {key === 'password' && (
          <form onSubmit={handleSubmit}>
            <div className="field">
              <label htmlFor="password">Mot de passe</label>
              <input
                id="password" name="password" type="password" value={form.password}
                onChange={update} required minLength={8} autoComplete="new-password"
              />
              <small className="text-muted">8 caractères minimum.</small>
            </div>

            <div className="field">
              <label htmlFor="password_confirmation">Confirmation</label>
              <input
                id="password_confirmation" name="password_confirmation" type="password"
                value={form.password_confirmation} onChange={update} required autoComplete="new-password"
              />
            </div>

            <div className="auth-nav">
              <button className="btn btn--primary" disabled={loading}>
                {loading ? 'Création…' : 'Créer mon compte'}
              </button>
            </div>
          </form>
        )}

        <p className="auth-switch text-muted">
          Déjà inscrit ? <Link to="/connexion">Se connecter</Link>
        </p>
      </div>
    </div>
  )
}
