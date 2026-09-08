<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Boîte d'envoi simulée
    |--------------------------------------------------------------------------
    |
    | Même logique que les paiements (§16.2.a) : la mécanique complète est en
    | place — Mailable, gabarit, file d'envoi — mais rien ne part réellement.
    | Le transport « simulated » (App\Mail\Transport\SimulatedTransport) écrit
    | chaque message en base au lieu de l'expédier, ce qui permet de démontrer
    | le parcours de bout en bout sans serveur SMTP ni adresse réelle.
    |
    | Pour passer en envoi réel : MAIL_MAILER=smtp dans .env. Aucun autre
    | changement de code n'est nécessaire, les Mailable sont de vrais Mailable.
    |
    */

    /*
    | Expose-t-on le message simulé à son destinataire ?
    |
    | À vrai, la réponse de « mot de passe oublié » transporte un bloc
    | `simulation` contenant le lien de prévisualisation du message, et la
    | route publique /mail-simule/{uuid} répond. C'est ce qui rend la
    | démonstration possible : sans boîte mail réelle, il faut bien un moyen
    | d'ouvrir le message.
    |
    | À FAUX EN PRODUCTION : n'importe qui pourrait alors lire le lien de
    | réinitialisation d'un compte dont il connaît seulement l'adresse.
    */
    'reveal' => (bool) env('MAIL_SIMULATION_REVEAL', true),

    /*
    | Nombre de messages conservés dans la boîte simulée. Au-delà, les plus
    | anciens sont effacés à chaque envoi : c'est un outil de démonstration,
    | pas un journal d'archivage.
    */
    'keep' => (int) env('MAIL_SIMULATION_KEEP', 200),

    /*
    | Base des liens de prévisualisation. Volontairement lue dans la config
    | plutôt que déduite de la requête : l'API est appelée à travers le proxy
    | Vite (frontend en 5173, backend en 8000) et `url()` renverrait alors un
    | hôte qui ne sert pas cette route.
    */
    'preview_base' => rtrim((string) env('APP_URL', 'http://localhost:8000'), '/'),

];
