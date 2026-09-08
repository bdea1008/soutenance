import { Routes, Route, Link, useLocation } from 'react-router-dom'
import Navbar from './components/Navbar'
import Footer from './components/Footer'
import ProtectedRoute from './components/ProtectedRoute'
import Home from './pages/Home'
import Projects from './pages/Projects'
import ProjectDetail from './pages/ProjectDetail'
import Login from './pages/Login'
import Register from './pages/Register'
import ForgotPassword from './pages/ForgotPassword'
import ResetPassword from './pages/ResetPassword'
import Dashboard from './pages/Dashboard'
import Pricing from './pages/Pricing'
import Kyc from './pages/Kyc'
import Notifications from './pages/Notifications'
import LegalProjects from './pages/LegalProjects'
import AdminOverview from './pages/admin/Overview'
import AdminUsers from './pages/admin/Users'
import AdminUserDetail from './pages/admin/UserDetail'
import AdminUserCreate from './pages/admin/UserCreate'
import AdminProjects from './pages/admin/Projects'
import AdminSubscriptions from './pages/admin/Subscriptions'
import AdminFinance from './pages/admin/Finance'
import AdminAnalytics from './pages/admin/Analytics'
import AdminDocuments from './pages/admin/Documents'
import AdminMailbox from './pages/admin/Mailbox'
import MyProjects from './pages/promoter/MyProjects'
import ProjectForm from './pages/promoter/ProjectForm'
import ProjectDossier from './pages/promoter/ProjectDossier'
import ProjectReports from './pages/promoter/ProjectReports'
import Subscription from './pages/promoter/Subscription'
import logoMark from './assets/logo-mark.png'
import './App.css'

function NotFound() {
  return (
    <div className="container section text-center">
      <img src={logoMark} alt="AndTabbax" className="brand-mark-lg" />
      <h1>404</h1>
      <p className="text-muted">Cette page n’existe pas.</p>
      <Link to="/" className="btn btn--primary">Retour à l’accueil</Link>
    </div>
  )
}

/**
 * Écrans d'identification : ni barre de navigation, ni pied de page.
 *
 * Ces deux pages ne servent qu'à une chose, et tout ce qui les entoure
 * (catalogue, tarifs, création de compte) ne fait qu'en détourner. Elles
 * portent leur propre logo et leur propre retour — voir `BackButton`.
 */
const BARE_PATHS = [
  '/connexion',
  '/inscription',
  // Le parcours « mot de passe oublié » relève des mêmes écrans : on n'y
  // fait qu'une chose, et l'utilisateur qui y arrive n'a pas de session.
  '/mot-de-passe-oublie',
  '/reinitialiser-mot-de-passe',
]

export default function App() {
  const { pathname } = useLocation()
  const isBare = BARE_PATHS.includes(pathname)

  return (
    <div style={{ display: 'flex', flexDirection: 'column', minHeight: '100vh' }}>
      {!isBare && <Navbar />}
      <main style={{ flex: 1 }}>
        <Routes>
          {/* Niveau 1 — public */}
          <Route path="/" element={<Home />} />
          <Route path="/projets" element={<Projects />} />
          <Route path="/tarifs" element={<Pricing />} />
          <Route path="/connexion" element={<Login />} />
          <Route path="/inscription" element={<Register />} />
          {/* Mot de passe oublié (§10) — public par nécessité : celui qui
              l'emprunte est précisément celui qui ne peut pas se connecter. */}
          <Route path="/mot-de-passe-oublie" element={<ForgotPassword />} />
          <Route path="/reinitialiser-mot-de-passe" element={<ResetPassword />} />

          {/* Niveau 2 — authentifié */}
          <Route path="/projets/:id" element={<ProtectedRoute><ProjectDetail /></ProtectedRoute>} />
          <Route path="/tableau-de-bord" element={<ProtectedRoute><Dashboard /></ProtectedRoute>} />
          <Route path="/verification" element={<ProtectedRoute><Kyc /></ProtectedRoute>} />
          <Route path="/notifications" element={<ProtectedRoute><Notifications /></ProtectedRoute>} />

          {/* Rôle juridique & conformité — lecture seule des projets (§5). */}
          <Route
            path="/verification-legale"
            element={<ProtectedRoute roles={['legal']}><LegalProjects /></ProtectedRoute>}
          />

          {/* Niveau 3 — espace promoteur.
              Réservé au seul rôle promoteur : un administrateur n'a pas de
              projets à lui ni d'abonnement à souscrire. Il supervise ceux des
              autres depuis /admin/projets. */}
          <Route
            path="/promoteur/projets"
            element={<ProtectedRoute roles={['promoter']}><MyProjects /></ProtectedRoute>}
          />
          <Route
            path="/promoteur/projets/nouveau"
            element={<ProtectedRoute roles={['promoter']}><ProjectForm /></ProtectedRoute>}
          />
          <Route
            path="/promoteur/projets/:id/modifier"
            element={<ProtectedRoute roles={['promoter']}><ProjectForm /></ProtectedRoute>}
          />
          <Route
            path="/promoteur/projets/:id/dossier"
            element={<ProtectedRoute roles={['promoter']}><ProjectDossier /></ProtectedRoute>}
          />
          <Route
            path="/promoteur/projets/:id/rapports"
            element={<ProtectedRoute roles={['promoter']}><ProjectReports /></ProtectedRoute>}
          />
          <Route
            path="/promoteur/abonnement"
            element={<ProtectedRoute roles={['promoter']}><Subscription /></ProtectedRoute>}
          />

          {/* Administration — back-office de supervision (§5) */}
          <Route
            path="/admin"
            element={<ProtectedRoute roles={['admin']}><AdminOverview /></ProtectedRoute>}
          />
          <Route
            path="/admin/utilisateurs"
            element={<ProtectedRoute roles={['admin']}><AdminUsers /></ProtectedRoute>}
          />
          <Route
            path="/admin/utilisateurs/nouveau"
            element={<ProtectedRoute roles={['admin']}><AdminUserCreate /></ProtectedRoute>}
          />
          <Route
            path="/admin/utilisateurs/:id"
            element={<ProtectedRoute roles={['admin']}><AdminUserDetail /></ProtectedRoute>}
          />
          <Route
            path="/admin/projets"
            element={<ProtectedRoute roles={['admin']}><AdminProjects /></ProtectedRoute>}
          />
          <Route
            path="/admin/abonnements"
            element={<ProtectedRoute roles={['admin']}><AdminSubscriptions /></ProtectedRoute>}
          />
          <Route
            path="/admin/finances"
            element={<ProtectedRoute roles={['admin']}><AdminFinance /></ProtectedRoute>}
          />
          <Route
            path="/admin/documents"
            element={<ProtectedRoute roles={['admin']}><AdminDocuments /></ProtectedRoute>}
          />
          <Route
            path="/admin/messages"
            element={<ProtectedRoute roles={['admin']}><AdminMailbox /></ProtectedRoute>}
          />
          <Route
            path="/admin/analyses"
            element={<ProtectedRoute roles={['admin']}><AdminAnalytics /></ProtectedRoute>}
          />

          <Route path="*" element={<NotFound />} />
        </Routes>
      </main>
      {!isBare && <Footer />}
    </div>
  )
}
