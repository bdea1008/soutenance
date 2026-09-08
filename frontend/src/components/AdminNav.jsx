import { NavLink } from 'react-router-dom'

/**
 * Barre d'onglets commune aux écrans d'administration.
 *
 * Chaque onglet porte la vue « plateforme entière » de sa notion : Projets =
 * tous les projets, Abonnements = tous les contrats. Rien ici n'est un espace
 * personnel — l'administrateur n'a ni projets à lui, ni abonnement à payer.
 */
const TABS = [
  { to: '/admin', label: 'Console', end: true },
  { to: '/admin/utilisateurs', label: 'Utilisateurs' },
  { to: '/admin/projets', label: 'Projets' },
  { to: '/admin/documents', label: 'Pièces KYC' },
  { to: '/admin/abonnements', label: 'Abonnements' },
  { to: '/admin/finances', label: 'Finances' },
  { to: '/admin/messages', label: 'Messages' },
  { to: '/admin/analyses', label: 'Analyses' },
]

export default function AdminNav({ title, subtitle, action }) {
  return (
    <div className="dash-head">
      <p className="eyebrow">Administration</p>
      <div className="promo-head">
        <h1 style={{ margin: 0 }}>{title}</h1>
        {action}
      </div>
      {subtitle && <p className="text-muted">{subtitle}</p>}

      <nav className="admin-tabs">
        {TABS.map((tab) => (
          <NavLink
            key={tab.to}
            to={tab.to}
            end={tab.end}
            className={({ isActive }) => `admin-tab ${isActive ? 'admin-tab--on' : ''}`}
          >
            {tab.label}
          </NavLink>
        ))}
      </nav>
    </div>
  )
}
