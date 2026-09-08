import { formatFCFA } from '../utils/format'

/**
 * Carte d'un palier d'abonnement promoteur.
 * `current` marque le palier auquel le promoteur est déjà abonné.
 */
export default function PlanCard({ plan, current = false, featured = false, action = null }) {
  return (
    <div className={`plan${featured ? ' plan--featured' : ''}${current ? ' plan--current' : ''}`}>
      {featured && <span className="plan__ribbon">Le plus choisi</span>}

      <h3 className="plan__name">{plan.label}</h3>
      <p className="plan__price">
        {formatFCFA(plan.monthly_price)}
        <span className="plan__period"> / mois</span>
      </p>

      <ul className="plan__features">
        {plan.features.map((feature) => (
          <li key={feature}>{feature}</li>
        ))}
      </ul>

      {current && <p className="badge badge--risk-low">Votre palier actuel</p>}
      {action}
    </div>
  )
}
