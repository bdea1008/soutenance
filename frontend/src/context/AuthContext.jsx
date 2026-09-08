import { createContext, useContext, useEffect, useState, useCallback } from 'react'
import client, { setToken, getToken, TOKEN_KEY, ROLE_MISMATCH_EVENT } from '../api/client'

const AuthContext = createContext(null)

export function AuthProvider({ children }) {
  const [user, setUser] = useState(null)
  const [loading, setLoading] = useState(true)

  // Au démarrage, si un token est présent, on récupère le profil.
  useEffect(() => {
    const token = getToken()
    if (!token) {
      setLoading(false)
      return
    }
    client
      .get('/auth/me')
      .then((res) => setUser(res.data.user))
      .catch(() => setToken(null))
      .finally(() => setLoading(false))
  }, [])

  const handleAuthSuccess = useCallback((data) => {
    setToken(data.access_token)
    setUser(data.user)
  }, [])

  const login = useCallback(async (credentials) => {
    const res = await client.post('/auth/login', credentials)
    handleAuthSuccess(res.data)
    return res.data.user
  }, [handleAuthSuccess])

  const register = useCallback(async (payload) => {
    const res = await client.post('/auth/register', payload)
    handleAuthSuccess(res.data)
    return res.data.user
  }, [handleAuthSuccess])

  const logout = useCallback(async () => {
    try {
      await client.post('/auth/logout')
    } catch {
      // On déconnecte localement même si l'appel échoue.
    }
    setToken(null)
    setUser(null)
  }, [])

  // Rafraîchit le profil (ex : après un investissement).
  const refreshUser = useCallback(async () => {
    const res = await client.get('/auth/me')
    setUser(res.data.user)
    return res.data.user
  }, [])

  /**
   * Resynchronisation du profil quand la session et l'écran divergent.
   *
   * Le jeton vit dans `localStorage`, partagé par tous les onglets ; l'objet
   * `user`, lui, est un état React propre à chaque onglet. Deux situations
   * les font diverger sans que rien ne le signale :
   *
   *  - un second compte est ouvert dans un autre onglet — le jeton est
   *    remplacé pour tout le monde, mais le premier onglet continue
   *    d'afficher l'ancien rôle ;
   *  - un administrateur change le rôle d'un compte connecté ailleurs.
   *
   * Dans les deux cas l'utilisateur voit un écran qu'il ne peut pas valider,
   * et le serveur refuse sur un rôle qu'il croit pourtant avoir. On relit
   * donc le profil dès que le serveur signale l'écart (`role_mismatch`) ou
   * que le jeton change dans un autre onglet.
   */
  useEffect(() => {
    function resync() {
      if (!getToken()) {
        setUser(null)
        return
      }

      client
        .get('/auth/me')
        .then((res) => setUser(res.data.user))
        .catch(() => {
          setToken(null)
          setUser(null)
        })
    }

    function onStorage(event) {
      if (event.key === TOKEN_KEY) resync()
    }

    window.addEventListener(ROLE_MISMATCH_EVENT, resync)
    window.addEventListener('storage', onStorage)

    return () => {
      window.removeEventListener(ROLE_MISMATCH_EVENT, resync)
      window.removeEventListener('storage', onStorage)
    }
  }, [])

  const value = { user, loading, login, register, logout, refreshUser, setUser }

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}

// eslint-disable-next-line react-refresh/only-export-components
export function useAuth() {
  const ctx = useContext(AuthContext)
  if (!ctx) throw new Error('useAuth doit être utilisé dans un AuthProvider')
  return ctx
}
