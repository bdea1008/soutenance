@extends('emails.layout', ['title' => 'Votre mot de passe a été modifié'])

@section('content')
    <h1 style="margin:0 0 14px; font-size:21px; line-height:1.3; color:#17241f;">
        Votre mot de passe a été modifié
    </h1>

    <p style="margin:0 0 14px; font-size:15px; line-height:1.65;">
        Bonjour {{ $user->first_name }},
    </p>

    <p style="margin:0 0 22px; font-size:15px; line-height:1.65;">
        Le mot de passe du compte <strong>{{ $user->email }}</strong> a été modifié le
        {{ $changedAt->timezone(config('app.timezone'))->translatedFormat('j F Y à H\hi') }}.
        Vous pouvez dès maintenant vous connecter avec votre nouveau mot de passe.
    </p>

    <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 22px;">
        <tr>
            <td style="background:#183630; border-radius:10px;">
                <a href="{{ $loginUrl }}"
                   style="display:inline-block; padding:13px 26px; font-size:15px; font-weight:600; color:#ffffff; text-decoration:none;">
                    Se connecter
                </a>
            </td>
        </tr>
    </table>

    {{-- Encart d'alerte : c'est le seul message de la chaîne qui puisse
         révéler une prise de contrôle du compte, il doit se voir. --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f4e5e3; border-radius:10px;">
        <tr>
            <td style="padding:16px 18px;">
                <p style="margin:0; font-size:14px; line-height:1.65; color:#a23c34;">
                    <strong>Vous n’êtes pas à l’origine de ce changement ?</strong>
                    Contactez immédiatement l’équipe AndTabbax : votre compte est peut-être compromis.
                </p>
            </td>
        </tr>
    </table>
@endsection
