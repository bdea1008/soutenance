<!DOCTYPE html>
{{--
    Racine du backend. Ce service n'expose qu'une API : la page d'accueil par
    défaut de Laravel n'y avait pas sa place — devant un jury, une adresse du
    projet ne doit pas afficher la vitrine d'un framework. On indique donc à
    quoi on est connecté et où se trouve l'interface.
--}}
<html lang="fr">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="icon" type="image/png" href="/favicon.png">
        <title>AndTabbax — API</title>

        <style>
            :root {
                --vert: #183630;
                --vert-fonce: #0f241f;
                --or: #e5c690;
                --or-fonce: #7a5517;
                --texte: #17241f;
                --discret: #655e50;
                --bord: #d9c9a8;
                --creme: #e3dac9;
            }
            * { box-sizing: border-box; }
            body {
                margin: 0; min-height: 100vh; display: grid; place-items: center;
                padding: 2rem 1.25rem;
                font: 16px/1.6 system-ui, -apple-system, "Segoe UI", sans-serif;
                color: var(--texte);
                background:
                    radial-gradient(900px 320px at 80% -10%, rgba(229, 198, 144, .22), transparent),
                    var(--creme);
            }
            .carte {
                width: 100%; max-width: 30rem; text-align: center;
                background: #fff; border: 1px solid var(--bord);
                border-radius: 16px; padding: 2.25rem 1.75rem;
                box-shadow: 0 1px 2px rgba(15, 36, 31, .07);
            }
            img { width: 88px; height: 88px; object-fit: contain; }
            h1 { margin: 1rem 0 .25rem; font-size: 1.5rem; }
            .slogan {
                margin: 0 0 1.25rem; font-size: .78rem; font-weight: 600;
                text-transform: uppercase; letter-spacing: .04em; color: var(--or-fonce);
            }
            p { margin: 0 0 1.25rem; color: var(--discret); }
            .liens { display: flex; flex-wrap: wrap; gap: .6rem; justify-content: center; }
            a {
                display: inline-block; padding: .55rem 1rem; border-radius: 10px;
                font-weight: 600; font-size: .9rem; text-decoration: none;
                border: 1px solid var(--bord); color: var(--vert-fonce);
            }
            a.principal { background: var(--vert); border-color: var(--vert); color: #fff; }
            code {
                font-size: .85rem; background: var(--creme); padding: .1rem .35rem;
                border-radius: 6px; color: var(--vert-fonce);
            }
            footer { margin-top: 1.5rem; font-size: .8rem; color: var(--discret); }
        </style>
    </head>
    <body>
        <main class="carte">
            <img src="/logo.png" alt="AndTabbax">
            <h1>AndTabbax — API</h1>
            <p class="slogan">Investir ensemble, bâtir l’avenir</p>

            <p>
                Vous êtes sur le service applicatif. L’interface se trouve sur
                <code>{{ $frontend }}</code>.
            </p>

            <div class="liens">
                <a class="principal" href="{{ $frontend }}">Ouvrir la plateforme</a>
                <a href="/api/health">État du service</a>
                <a href="/api/stats">Chiffres publics</a>
            </div>

            <footer>Plateforme de co-investissement immobilier — projet de soutenance.</footer>
        </main>
    </body>
</html>
