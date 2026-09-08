import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import client from '../api/client'
import { formatFCFACompact } from '../utils/format'
import ProjectCard from '../components/ProjectCard'
import Icon from '../components/Icon'
import logoMark from '../assets/logo-mark.png'

const TRUST = [
  { icon: 'search', title: 'Transparence', text: 'Suivi d’avancement des chantiers, documents centralisés et historique des contributions.' },
  { icon: 'spark', title: 'Scoring IA', text: 'Chaque projet reçoit un score de confiance et une analyse de risque automatisée.' },
  { icon: 'phone', title: 'Mobile Money', text: 'Contributions et abonnements via Wave et Orange Money, pensés pour l’Afrique.' },
  { icon: 'users', title: 'Co-investissement', text: 'Investissez collectivement dès de petits montants, sans frais pour l’investisseur.' },
]

function Stat({ value, label }) {
  return (
    <div className="stat">
      <div className="stat__value">{value}</div>
      <div className="stat__label">{label}</div>
    </div>
  )
}

export default function Home() {
  const [stats, setStats] = useState(null)
  const [projects, setProjects] = useState([])
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    Promise.all([
      client.get('/stats'),
      client.get('/projects', { params: { per_page: 6 } }),
    ])
      .then(([s, p]) => {
        setStats(s.data)
        setProjects(p.data.data)
      })
      .catch(() => {})
      .finally(() => setLoading(false))
  }, [])

  return (
    <>
      {/* Hero */}
      <section className="hero">
        <div className="container hero__inner">
          {/* Le fond du hero est vert profond : le bleu nuit du logo y
              disparaîtrait, d'où la pastille claire. */}
          <span className="logo-chip logo-chip--lg hero__logo">
            <img src={logoMark} alt="" />
          </span>
          <p className="hero__slogan">Investir ensemble. Construire ensemble.</p>
          <h1>Le co-investissement immobilier, accessible à tous au Sénégal.</h1>
          <p>
            AndTabbax connecte investisseurs et promoteurs pour financer collectivement
            des projets immobiliers, en toute transparence et avec l’appui de l’intelligence artificielle.
          </p>
          <div className="hero__actions">
            <Link to="/inscription" className="btn btn--accent">Commencer à investir</Link>
            <Link to="/projets" className="hero__link">Découvrir les projets</Link>
          </div>
        </div>
      </section>

      {/* Statistiques */}
      <section className="section">
        <div className="container">
          <div className="grid grid--4">
            <Stat value={stats ? stats.projects_total : '—'} label="Projets ouverts" />
            <Stat value={stats ? formatFCFACompact(stats.total_raised) : '—'} label="Montant levé" />
            <Stat value={stats ? stats.investors_count : '—'} label="Investisseurs" />
            <Stat value={stats ? stats.promoters_count : '—'} label="Promoteurs" />
          </div>
        </div>
      </section>

      {/* Aperçu projets */}
      <section className="section section--tint">
        <div className="container">
          <div className="section__head">
            <p className="eyebrow">Opportunités</p>
            <h2>Des projets immobiliers sélectionnés</h2>
            <p className="text-muted">
              Un aperçu des projets de la plateforme. Créez un compte pour accéder au détail complet et investir.
            </p>
          </div>

          {loading ? (
            <div className="spinner" />
          ) : projects.length === 0 ? (
            <p className="text-muted">Aucun projet public pour le moment.</p>
          ) : (
            <div className="grid grid--3">
              {projects.map((p) => <ProjectCard key={p.id} project={p} />)}
            </div>
          )}

          <div className="text-center" style={{ marginTop: '2rem' }}>
            <Link to="/projets" className="btn btn--primary">Voir tous les projets</Link>
          </div>
        </div>
      </section>

      {/* Confiance */}
      <section className="section">
        <div className="container">
          <div className="section__head section__head--center">
            <p className="eyebrow">Pourquoi AndTabbax</p>
            <h2>Une infrastructure de confiance</h2>
          </div>
          <div className="grid grid--2">
            {TRUST.map((f) => (
              <div key={f.title} className="feature">
                <div className="feature__icon"><Icon name={f.icon} size={22} /></div>
                <div>
                  <h3>{f.title}</h3>
                  <p className="text-muted">{f.text}</p>
                </div>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* À propos */}
      <section className="section section--tint">
        <div className="container" style={{ maxWidth: 760 }}>
          <p className="eyebrow">À propos</p>
          <h2>Démocratiser l’investissement immobilier</h2>
          <p className="text-muted">
            Au Sénégal, l’immobilier est l’un des placements les plus sûrs mais reste difficile
            d’accès : coûts élevés, manque de transparence, risques d’arnaques. AndTabbax lève ces
            barrières en permettant à chacun — y compris la diaspora — de co-investir dans des projets
            vérifiés, de suivre l’avancement des chantiers à distance et de s’appuyer sur des outils
            intelligents d’analyse des risques et de la rentabilité.
          </p>
        </div>
      </section>
    </>
  )
}
