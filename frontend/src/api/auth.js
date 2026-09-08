import client from './client'

/**
 * Appels d'authentification qui ne passent pas par AuthContext.
 *
 * Le contexte porte la session (connexion, profil, déconnexion). Le parcours
 * « mot de passe oublié » n'en ouvre aucune : il se déroule entièrement hors
 * session, jeton d'email à la main. D'où ce module à part.
 */

/** Étape 1 — demander un lien de réinitialisation. */
export function requestPasswordReset(email) {
  return client.post('/auth/password/forgot', { email }).then((r) => r.data)
}

/**
 * Étape 2 — le lien est-il encore valable ?
 * Répond toujours 200 : c'est `valid` qui porte la réponse, pas le code HTTP.
 */
export function checkResetToken({ email, token }) {
  return client.get('/auth/password/check', { params: { email, token } }).then((r) => r.data)
}

/** Étape 3 — poser le nouveau mot de passe. */
export function resetPassword({ email, token, password, passwordConfirmation }) {
  return client
    .post('/auth/password/reset', {
      email,
      token,
      password,
      password_confirmation: passwordConfirmation,
    })
    .then((r) => r.data)
}
