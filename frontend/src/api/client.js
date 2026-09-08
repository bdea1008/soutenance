import axios from 'axios'

/**
 * Client HTTP vers l'API AndTabbax.
 * En dev, /api est relayé vers Laravel via le proxy Vite (voir vite.config.js).
 */
const client = axios.create({
  baseURL: import.meta.env.VITE_API_URL || '/api',
  headers: { Accept: 'application/json' },
})

export const TOKEN_KEY = 'andtabbax_token'

/** Émis quand la session ne correspond plus au rôle affiché — voir plus bas. */
export const ROLE_MISMATCH_EVENT = 'andtabbax:role-mismatch'

export function getToken() {
  return localStorage.getItem(TOKEN_KEY)
}

export function setToken(token) {
  if (token) localStorage.setItem(TOKEN_KEY, token)
  else localStorage.removeItem(TOKEN_KEY)
}

// Injecte le token JWT sur chaque requête.
client.interceptors.request.use((config) => {
  const token = getToken()
  if (token) config.headers.Authorization = `Bearer ${token}`
  return config
})

// Sur 401, purge le token (session expirée / invalide).
//
// Sur 403 `account_suspended`, la session est techniquement valide mais le
// compte a été désactivé par l'administration : toutes les requêtes suivantes
// échoueraient de la même façon. On ferme donc la session et on renvoie vers
// l'écran de connexion, qui expliquera le refus, plutôt que de laisser
// l'utilisateur sur une page qui se remplit d'erreurs.
client.interceptors.response.use(
  (res) => res,
  (error) => {
    const { status, data } = error.response ?? {}

    if (status === 401) {
      setToken(null)
    }

    if (status === 403 && data?.code === 'account_suspended') {
      setToken(null)
      if (window.location.pathname !== '/connexion') {
        window.location.assign('/connexion?suspendu=1')
      }
    }

    // Le serveur refuse sur le rôle : l'interface affiche donc un rôle que la
    // session n'a pas. Ça arrive quand un administrateur change le rôle d'un
    // compte connecté ailleurs, ou quand un second compte est ouvert dans un
    // autre onglet (le jeton est partagé, l'état React non). On demande au
    // contexte d'aller relire le profil : l'interface se corrige d'elle-même
    // au lieu de laisser l'utilisateur sur un écran qu'il ne peut pas valider.
    if (status === 403 && data?.code === 'role_mismatch') {
      window.dispatchEvent(new CustomEvent(ROLE_MISMATCH_EVENT))
    }

    return Promise.reject(error)
  },
)

/** Extrait un message d'erreur lisible d'une réponse axios. */
export function errorMessage(error, fallback = 'Une erreur est survenue.') {
  const data = error.response?.data
  if (data?.errors) {
    return Object.values(data.errors).flat().join(' ')
  }
  return data?.message || fallback
}

export default client
