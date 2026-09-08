import client from './client'

/**
 * Appels du back-office administrateur.
 *
 * Regroupés ici plutôt que dispersés dans les pages : plusieurs écrans
 * partagent les mêmes lectures (la console et l'annuaire lisent tous deux les
 * comptes, la fiche utilisateur renvoie vers la modération des pièces), et le
 * préfixe /admin doit rester au même endroit.
 */

// --- Console -------------------------------------------------------------

export function fetchOverview() {
  return client.get('/admin/overview').then((r) => r.data)
}

// --- Comptes -------------------------------------------------------------

export function fetchUsers(params) {
  return client.get('/admin/users', { params }).then((r) => r.data)
}

export function fetchUserStats() {
  return client.get('/admin/users/stats').then((r) => r.data)
}

export function fetchUser(id) {
  return client.get(`/admin/users/${id}`).then((r) => r.data)
}

export function createUser(payload) {
  return client.post('/admin/users', payload).then((r) => r.data)
}

export function deleteUser(id) {
  return client.delete(`/admin/users/${id}`).then((r) => r.data)
}

/** `reason` est obligatoire côté API lorsqu'on désactive. */
export function setUserStatus(id, active, reason) {
  return client
    .patch(`/admin/users/${id}/status`, { active, reason: reason || undefined })
    .then((r) => r.data)
}

export function setUserRole(id, role) {
  return client.patch(`/admin/users/${id}/role`, { role }).then((r) => r.data)
}

// --- Projets -------------------------------------------------------------

export function fetchProjects(params) {
  return client.get('/admin/projects', { params }).then((r) => r.data)
}

export function fetchProjectStats() {
  return client.get('/admin/projects/stats').then((r) => r.data)
}

/** `decision` : 'approve' | 'cancel' | 'restore'. Motif requis pour 'cancel'. */
export function moderateProject(id, decision, reason) {
  return client
    .post(`/admin/projects/${id}/moderate`, { decision, reason: reason || undefined })
    .then((r) => r.data)
}

// --- Finances ------------------------------------------------------------

export function fetchFinanceSummary() {
  return client.get('/admin/finance/summary').then((r) => r.data)
}

export function fetchSubscriptions(params) {
  return client.get('/admin/finance/subscriptions', { params }).then((r) => r.data)
}

export function fetchPayments(params) {
  return client.get('/admin/finance/payments', { params }).then((r) => r.data)
}

// --- Boîte d'envoi simulée -----------------------------------------------
// Les emails ne partent pas réellement tant qu'aucun serveur de messagerie
// n'est branché (config/mail_simulation.php côté backend) : cet écran est le
// seul endroit où l'on peut constater ce que la plateforme a « envoyé ».

export function fetchEmails(params) {
  return client.get('/admin/emails', { params }).then((r) => r.data)
}

export function fetchEmail(id) {
  return client.get(`/admin/emails/${id}`).then((r) => r.data)
}

export function clearEmails() {
  return client.delete('/admin/emails').then((r) => r.data)
}
