<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $email->subject }} — Message simulé AndTabbax</title>
    <link rel="icon" href="/favicon.png">
    <style>
        :root {
            --primary: #183630;
            --bg: #e3dac9;
            --surface: #ffffff;
            --border: #d9c9a8;
            --text: #17241f;
            --muted: #655e50;
            --gold: #e5c690;
            --gold-dark: #7a5517;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: var(--bg);
            color: var(--text);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            line-height: 1.6;
        }
        .wrap { max-width: 720px; margin: 0 auto; padding: 1.5rem 1rem 3rem; }

        /* Bandeau : dit d'emblée que rien n'a réellement été expédié. Sans
           lui, la page pourrait passer pour une vraie boîte de réception. */
        .sim-banner {
            background: var(--primary);
            color: #fff;
            border-radius: 12px;
            padding: 0.9rem 1.1rem;
            margin-bottom: 1.25rem;
            font-size: 0.88rem;
        }
        .sim-banner b { color: var(--gold); }

        .mail { background: var(--surface); border: 1px solid var(--border); border-radius: 12px; overflow: hidden; }
        .mail__head { padding: 1.1rem 1.25rem; border-bottom: 1px solid var(--border); }
        .mail__subject { margin: 0 0 0.6rem; font-size: 1.15rem; line-height: 1.35; }
        .mail__meta { display: grid; grid-template-columns: 5.5rem 1fr; gap: 0.15rem 0.5rem; font-size: 0.85rem; }
        .mail__meta dt { color: var(--muted); }
        .mail__meta dd { margin: 0; word-break: break-word; }

        .tag {
            display: inline-block; padding: 0.1rem 0.5rem; border-radius: 999px;
            background: #fbf7ef; color: var(--gold-dark);
            font-size: 0.74rem; font-weight: 700; letter-spacing: 0.03em; text-transform: uppercase;
        }

        /* Le message est rendu dans une iframe : ses styles en ligne et ses
           tables de mise en page ne doivent pas déteindre sur la page qui
           l'entoure, et c'est aussi ce que ferait un client de messagerie. */
        iframe { display: block; width: 100%; border: 0; background: var(--bg); }

        .foot { margin-top: 1.25rem; font-size: 0.82rem; color: var(--muted); text-align: center; }
        .foot a { color: var(--primary); }
    </style>
</head>
<body>
<div class="wrap">

    <div class="sim-banner">
        <b>Envoi simulé — AndTabbax</b><br>
        Ce message a été composé par l’application puis déposé dans une boîte d’envoi interne
        au lieu d’être remis à un serveur de messagerie. Voici ce que
        <strong>{{ $email->to_email }}</strong> aurait reçu.
    </div>

    <div class="mail">
        <div class="mail__head">
            <span class="tag">{{ \App\Models\SimulatedEmail::contextLabel($email->context) }}</span>
            <h1 class="mail__subject">{{ $email->subject }}</h1>
            <dl class="mail__meta">
                <dt>De</dt>
                <dd>{{ $email->from_name ? $email->from_name.' <'.$email->from_email.'>' : $email->from_email }}</dd>
                <dt>À</dt>
                <dd>{{ $email->to_name ? $email->to_name.' <'.$email->to_email.'>' : $email->to_email }}</dd>
                <dt>Date</dt>
                <dd>{{ $email->created_at?->translatedFormat('j F Y à H\hi') }}</dd>
            </dl>
        </div>

        @if ($email->body_html)
            <iframe title="Contenu du message" srcdoc="{{ $email->body_html }}" height="560"></iframe>
        @else
            <pre style="margin:0; padding:1.25rem; white-space:pre-wrap; font-size:0.9rem;">{{ $email->body_text }}</pre>
        @endif
    </div>

    <p class="foot">
        Retour à <a href="{{ rtrim(config('app.frontend_url'), '/') }}">l’application AndTabbax</a>
    </p>

</div>

<script>
    // Ajuste la hauteur de l'iframe au contenu réel : une hauteur fixe
    // couperait les messages longs ou laisserait un grand vide sous les courts.
    var frame = document.querySelector('iframe')
    if (frame) {
        frame.addEventListener('load', function () {
            try {
                frame.height = frame.contentDocument.documentElement.scrollHeight
            } catch (e) {
                // srcdoc partage l'origine, donc ceci ne devrait pas arriver ;
                // en cas contraire la hauteur par défaut reste utilisable.
            }
        })
    }
</script>
</body>
</html>
