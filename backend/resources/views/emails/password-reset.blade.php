@extends('emails.layout', ['title' => 'Réinitialisation de votre mot de passe'])

@section('content')
    <h1 style="margin:0 0 14px; font-size:21px; line-height:1.3; color:#17241f;">
        Réinitialisation de votre mot de passe
    </h1>

    <p style="margin:0 0 14px; font-size:15px; line-height:1.65;">
        Bonjour {{ $user->first_name }},
    </p>

    <p style="margin:0 0 22px; font-size:15px; line-height:1.65;">
        Vous avez demandé à définir un nouveau mot de passe pour le compte
        <strong>{{ $user->email }}</strong>. Cliquez sur le bouton ci-dessous pour le choisir.
    </p>

    {{-- Bouton en table : les <a> stylés en bloc s'effondrent sous Outlook. --}}
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 22px;">
        <tr>
            <td style="background:#183630; border-radius:10px;">
                <a href="{{ $resetUrl }}"
                   style="display:inline-block; padding:13px 26px; font-size:15px; font-weight:600; color:#ffffff; text-decoration:none;">
                    Choisir un nouveau mot de passe
                </a>
            </td>
        </tr>
    </table>

    <p style="margin:0 0 22px; font-size:14px; line-height:1.65; color:#655e50;">
        Ce lien est valable <strong>{{ $expiresInMinutes }} minutes</strong> et ne peut servir qu’une fois.
        Passé ce délai, refaites une demande depuis la page de connexion.
    </p>

    <hr style="border:0; border-top:1px solid #d9c9a8; margin:0 0 18px;">

    <p style="margin:0 0 10px; font-size:13px; line-height:1.65; color:#655e50;">
        Si le bouton ne fonctionne pas, copiez cette adresse dans votre navigateur :
    </p>
    <p style="margin:0 0 20px; font-size:12px; line-height:1.6; word-break:break-all;">
        <a href="{{ $resetUrl }}" style="color:#0a56d3;">{{ $resetUrl }}</a>
    </p>

    <p style="margin:0; font-size:13px; line-height:1.65; color:#655e50;">
        <strong>Vous n’êtes pas à l’origine de cette demande ?</strong> Ignorez ce message :
        votre mot de passe actuel reste valable et aucune modification n’a été faite sur votre compte.
    </p>
@endsection
