import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import client from '../api/client'
import { useAuth } from '../context/AuthContext'
import PlanCard from '../components/PlanCard'
import { AcceptedMethods } from '../components/PaymentMethod'

/**
 * Page Tarifs — niveau 1 (visiteur). Le modèle économique repose sur
 * l'abonnement promoteur : investir reste sans frais (§16.2).
 */
export default function Pricing() {
  const { user } = useAuth()
  const [plans, setPlans] = useState([])
  const [providers, setProviders] = useState([])
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    client
      .get('/subscription-plans')
      .then((res) => {
        setPlans(res.data.plans)
        setProviders(res.data.providers)
      })
      .catch(() => setPlans([]))
      .finally(() => setLoading(false))
  }, [])

  // L'abonnement est une offre faite aux promoteurs. Un administrateur n'en
  // souscrit pas : il consulte cette page comme une page publique.
  const isPromoter = user?.role === 'promoter'

  // Deux publics, deux grilles : un particulier qui finance sa maison n'a rien
  // à faire dans les paliers professionnels, et réciproquement. Un promoteur
  // connecté ne voit que la sienne, un visiteur voit les deux.
  const groups = [
    {
      type: 'individual',
      title: 'Vous financez votre propre bien',
      lead: 'Un projet à la fois, le vôtre. Dossier fondé sur vos revenus et votre apport.',
    },
    {
      type: 'company',
      title: 'Vous êtes promoteur immobilier',
      lead: 'Plusieurs opérations en parallèle, dossier fondé sur vos comptes et vos références.',
    },
  ]
    .map((group) => ({ ...group, plans: plans.filter((p) => p.promoter_types?.includes(group.type)) }))
    .filter((group) => group.plans.length > 0)
    .filter((group) => !isPromoter || group.type === user.promoter_type)

  return (
    <section className="section">
      <div className="container">
        <div className="section__head section__head--center">
          <p className="eyebrow">Tarifs</p>
          <h2>Abonnements promoteur</h2>
          <p className="text-muted">
            Publier un projet sur AndTabbax nécessite un abonnement mensuel.
            Côté investisseur, la plateforme reste <b>sans frais</b>.
          </p>
        </div>

        {loading ? (
          <div className="spinner" />
        ) : (
          <>
            {groups.map((group) => (
              <div key={group.type} style={{ marginBottom: '2.5rem' }}>
                {/* L'intertitre disparaît quand il n'y a qu'une grille à
                    montrer : le promoteur connecté sait déjà qui il est. */}
                {groups.length > 1 && (
                  <div className="section__head section__head--center" style={{ marginBottom: '1.25rem' }}>
                    <h3 style={{ marginBottom: '0.25rem' }}>{group.title}</h3>
                    <p className="text-muted" style={{ margin: 0 }}>{group.lead}</p>
                  </div>
                )}

                {/* Un palier unique ne doit pas s'étirer sur toute la largeur :
                    on le ramène à la largeur d'une colonne de la grille à 3. */}
                <div
                  className="grid grid--3 plan-grid"
                  style={group.plans.length === 1 ? { maxWidth: 380, margin: '0 auto' } : undefined}
                >
                  {group.plans.map((plan) => (
                    <PlanCard
                      key={plan.tier}
                      plan={plan}
                      featured={plan.tier === 'premium'}
                      action={
                        isPromoter ? (
                          <Link to="/promoteur/abonnement" className="btn btn--primary btn--block">
                            Gérer mon abonnement
                          </Link>
                        ) : user ? null : (
                          <Link to="/inscription" className="btn btn--primary btn--block">
                            {group.type === 'individual' ? 'Financer mon projet' : 'Devenir promoteur'}
                          </Link>
                        )
                      }
                    />
                  ))}
                </div>
              </div>
            ))}

            {providers.length > 0 && (
              <div className="pricing-trust">
                {/* Les logos disent le moyen de paiement mieux que son nom
                    écrit : c'est l'icône Wave ou Orange Money qu'on reconnaît
                    au premier coup d'œil, avant de lire quoi que ce soit. */}
                <AcceptedMethods providers={providers} />
                <p className="text-muted" style={{ margin: 0, fontSize: '0.82rem' }}>
                  Paiement mensuel, sans reconduction automatique.
                  {' '}Les paiements sont simulés durant cette phase de démonstration.
                </p>
              </div>
            )}

            {user?.role === 'investor' && (
              <div className="alert alert--info" style={{ maxWidth: 620, margin: '2rem auto 0' }}>
                Vous êtes connecté en tant qu’investisseur : aucun abonnement n’est requis pour investir.
              </div>
            )}

            {/* L'administrateur ne souscrit pas : cette page est l'offre faite
                aux promoteurs, son affaire à lui c'est le parc de contrats. */}
            {user?.role === 'admin' && (
              <div className="alert alert--info" style={{ maxWidth: 620, margin: '2rem auto 0' }}>
                Vous consultez la grille tarifaire telle que la voient les promoteurs.
                Les contrats souscrits sont dans{' '}
                <Link to="/admin/abonnements">l’onglet Abonnements de l’administration</Link>.
              </div>
            )}

            {/* Le rôle juridique ne souscrit pas davantage : son affaire est
                la conformité des projets, pas la tarification. */}
            {user?.role === 'legal' && (
              <div className="alert alert--info" style={{ maxWidth: 620, margin: '2rem auto 0' }}>
                Vous consultez la grille tarifaire telle que la voient les promoteurs.
                {' '}<Link to="/verification-legale">Retour à la vérification des projets</Link>.
              </div>
            )}
          </>
        )}
      </div>
    </section>
  )
}
