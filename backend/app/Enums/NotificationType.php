<?php

namespace App\Enums;

/**
 * Catalogue des notifications applicatives (§7.7).
 *
 * Chaque type porte son propre libellé : la rédaction reste au même endroit que
 * la définition, ce qui évite que le même événement soit formulé de trois
 * façons différentes selon le contrôleur qui le déclenche.
 */
enum NotificationType: string
{
    // Vérification KYC (§7.1)
    case DocumentApproved = 'kyc.document_approved';
    case DocumentRejected = 'kyc.document_rejected';
    case KycVerified = 'kyc.verified';

    // Investissement (§7.3)
    case InvestmentConfirmed = 'investment.confirmed';   // → investisseur
    case InvestmentReceived = 'investment.received';     // → promoteur

    // Cycle de vie du projet
    case ProjectFunded = 'project.funded';

    // Avis d'investisseur (§2, extension d'« Investir dans un projet »)
    case ProjectReviewed = 'project.reviewed';

    // Décisions administratives sur un projet (§5, supervision de la plateforme)
    case ProjectApproved = 'project.approved';
    case ProjectCancelled = 'project.cancelled';
    case ProjectRestored = 'project.restored';

    // Décisions administratives sur un compte
    case AccountSuspended = 'account.suspended';
    case AccountReactivated = 'account.reactivated';

    // Sécurité du compte (§10) — déclenché par la réinitialisation du mot de passe
    case PasswordChanged = 'account.password_changed';

    // Suivi de chantier (§7.5)
    case ReportPublished = 'report.published';

    // Abonnement promoteur (§16.2.a)
    case SubscriptionActivated = 'subscription.activated';
    case SubscriptionCancelled = 'subscription.cancelled';

    /**
     * Nom de l'icône affichée dans la liste — pas le dessin lui-même.
     *
     * L'API décrit la nature de l'événement ; c'est l'interface qui décide de
     * sa représentation (`components/Icon.jsx`). Renvoyer un emoji faisait
     * transiter un choix graphique par le réseau, avec un rendu différent selon
     * le système du destinataire.
     */
    public function icon(): string
    {
        return match ($this) {
            self::DocumentApproved, self::KycVerified => 'check',
            self::DocumentRejected => 'alert',
            self::InvestmentConfirmed, self::InvestmentReceived => 'wallet',
            self::ProjectFunded => 'target',
            self::ProjectReviewed => 'star',
            self::ProjectApproved, self::AccountReactivated => 'check',
            self::ProjectCancelled, self::AccountSuspended => 'ban',
            self::PasswordChanged => 'lock',
            self::ProjectRestored => 'undo',
            self::ReportPublished => 'building',
            self::SubscriptionActivated => 'star',
            self::SubscriptionCancelled => 'ban',
        };
    }

    /**
     * Compose le titre et le corps du message à partir du contexte fourni au
     * déclenchement.
     *
     * @param  array<string, mixed>  $data
     * @return array{title: string, body: string}
     */
    public function compose(array $data): array
    {
        $project = $data['project_title'] ?? 'votre projet';
        $document = $data['document_type'] ?? 'votre pièce';
        $amount = isset($data['amount']) ? number_format((float) $data['amount'], 0, ',', ' ').' FCFA' : '';

        return match ($this) {
            self::DocumentApproved => [
                'title' => 'Pièce validée',
                'body' => "Votre {$document} a été validée par nos équipes.",
            ],
            self::DocumentRejected => [
                'title' => 'Pièce rejetée',
                'body' => "Votre {$document} a été rejetée."
                    .(isset($data['review_note']) ? " Motif : {$data['review_note']}" : '')
                    .' Vous pouvez déposer une nouvelle version.',
            ],
            self::KycVerified => [
                'title' => 'Identité vérifiée',
                'body' => 'Votre dossier est complet. Vous avez désormais accès à '
                    .($data['role'] === UserRole::Promoter->value
                        ? 'la publication de projets.'
                        : "l'investissement."),
            ],
            self::InvestmentConfirmed => [
                'title' => 'Investissement enregistré',
                'body' => "Votre investissement de {$amount} dans « {$project} » est enregistré.",
            ],
            self::InvestmentReceived => [
                'title' => 'Nouvel investissement',
                'body' => "« {$project} » vient de recevoir {$amount}.",
            ],
            self::ProjectFunded => [
                'title' => 'Objectif atteint',
                'body' => "« {$project} » est intégralement financé. Le chantier peut démarrer.",
            ],
            self::ProjectReviewed => [
                'title' => 'Nouvel avis',
                'body' => "« {$project} » a reçu une note de ".($data['rating'] ?? '?').'/5'
                    .(isset($data['comment']) && $data['comment'] !== '' ? " : « {$data['comment']} »" : '.'),
            ],
            self::ProjectApproved => [
                'title' => 'Projet validé',
                'body' => "« {$project} » a été validé par l'administration et est en ligne.",
            ],
            self::ProjectCancelled => [
                'title' => 'Projet retiré',
                'body' => "« {$project} » a été retiré de la plateforme par l'administration."
                    .(isset($data['reason']) ? " Motif : {$data['reason']}" : ''),
            ],
            self::ProjectRestored => [
                'title' => 'Projet rétabli',
                'body' => "« {$project} » a été rétabli par l'administration"
                    .(isset($data['status_label']) ? " (statut : {$data['status_label']})" : '').'.',
            ],
            self::AccountSuspended => [
                'title' => 'Compte désactivé',
                'body' => "Votre compte a été désactivé par l'administration."
                    .(isset($data['reason']) ? " Motif : {$data['reason']}" : '')
                    .' Vous ne pouvez plus accéder à la plateforme.',
            ],
            self::AccountReactivated => [
                'title' => 'Compte réactivé',
                'body' => 'Votre compte a été réactivé. Vous retrouvez l\'accès à la plateforme.',
            ],
            self::PasswordChanged => [
                'title' => 'Mot de passe modifié',
                'body' => 'Le mot de passe de votre compte vient d\'être modifié. '
                    ."Si vous n'êtes pas à l'origine de ce changement, contactez-nous immédiatement.",
            ],
            self::ReportPublished => [
                'title' => 'Avancement du chantier',
                'body' => "« {$project} » : "
                    .($data['report_title'] ?? 'nouveau rapport')
                    .' — '.($data['progress'] ?? 0).' % réalisés.',
            ],
            self::SubscriptionActivated => [
                'title' => 'Abonnement actif',
                'body' => 'Votre abonnement '.($data['tier'] ?? '').' est actif. '
                    .'Vous pouvez publier vos projets.',
            ],
            self::SubscriptionCancelled => [
                'title' => 'Abonnement résilié',
                'body' => 'Votre abonnement a été résilié. Vos projets en ligne restent visibles '
                    ."jusqu'à leur terme, mais vous ne pouvez plus en publier de nouveaux.",
            ],
        };
    }
}
