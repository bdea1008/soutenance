<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? config('app.name') }}</title>
</head>
{{--
    Gabarit commun des emails AndTabbax.

    Tout est en tables et en styles en ligne : les clients de messagerie
    ignorent les feuilles de style externes, les variables CSS et une bonne
    partie de flexbox. Les jetons de la charte (vert profond, ivoire, or) sont
    donc recopiés en dur ici — c'est le seul endroit de l'application où on
    s'autorise à dupliquer des couleurs, faute de pouvoir lire :root.

    Pas d'image distante : un logo en <img> se transforme en cadre vide chez
    tout destinataire qui bloque les images externes (le réglage par défaut de
    Gmail et d'Outlook). Le mot-symbole en or sur le bandeau vert reprend le
    traitement de la barre de navigation.
--}}
<body style="margin:0; padding:0; background:#e3dac9; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; color:#17241f;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#e3dac9;">
        <tr>
            <td align="center" style="padding:32px 16px;">

                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:560px; background:#ffffff; border-radius:12px; overflow:hidden; border:1px solid #d9c9a8;">

                    {{-- Bandeau de marque --}}
                    <tr>
                        <td style="background:#183630; padding:22px 28px;">
                            <span style="font-size:19px; font-weight:700; letter-spacing:0.02em; color:#e5c690;">AndTabbax</span>
                            <span style="display:block; margin-top:3px; font-size:12px; color:#b9c9c2;">Co-investissement immobilier</span>
                        </td>
                    </tr>

                    {{-- Corps --}}
                    <tr>
                        <td style="padding:28px;">
                            @yield('content')
                        </td>
                    </tr>

                    {{-- Pied --}}
                    <tr>
                        <td style="background:#f7f3ea; padding:18px 28px; border-top:1px solid #d9c9a8;">
                            <p style="margin:0; font-size:12px; line-height:1.6; color:#655e50;">
                                Message automatique — merci de ne pas y répondre.<br>
                                © {{ date('Y') }} AndTabbax. Tous droits réservés.
                            </p>
                        </td>
                    </tr>

                </table>

            </td>
        </tr>
    </table>
</body>
</html>
