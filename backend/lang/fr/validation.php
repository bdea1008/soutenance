<?php

/*
|--------------------------------------------------------------------------
| Messages de validation — français
|--------------------------------------------------------------------------
|
| APP_LOCALE vaut « fr » mais Laravel ne livre que les messages anglais :
| sans ce fichier, un mot de passe trop court renvoyait « The password field
| must be at least 8 characters » au milieu d'une interface entièrement en
| français.
|
| Volontairement partiel : seules les règles réellement utilisées par les
| FormRequest de l'application sont traduites. Les autres retombent sur
| l'anglais (APP_FALLBACK_LOCALE), ce qui reste préférable à une traduction
| exhaustive recopiée sans être relue. À compléter au fur et à mesure que de
| nouvelles règles apparaissent.
|
| Les messages propres à un champ précis restent dans le FormRequest
| concerné (méthode `messages()`) : ils y sont au plus près de la règle
| qu'ils expliquent.
|
*/

return [

    'accepted' => 'Le champ :attribute doit être accepté.',
    'after' => 'Le champ :attribute doit être une date postérieure au :date.',
    'after_or_equal' => 'Le champ :attribute doit être une date postérieure ou égale au :date.',
    'array' => 'Le champ :attribute doit être une liste.',
    'before' => 'Le champ :attribute doit être une date antérieure au :date.',
    'before_or_equal' => 'Le champ :attribute doit être une date antérieure ou égale au :date.',
    'boolean' => 'Le champ :attribute doit être vrai ou faux.',
    'confirmed' => 'La confirmation du champ :attribute ne correspond pas.',
    'date' => 'Le champ :attribute n’est pas une date valide.',
    'declined' => 'Le champ :attribute doit être refusé.',
    'different' => 'Les champs :attribute et :other doivent être différents.',
    'digits' => 'Le champ :attribute doit contenir :digits chiffres.',
    'email' => 'Le champ :attribute doit être une adresse email valide.',
    'exists' => 'La valeur choisie pour :attribute est invalide.',
    'file' => 'Le champ :attribute doit être un fichier.',
    'filled' => 'Le champ :attribute doit avoir une valeur.',
    'image' => 'Le champ :attribute doit être une image.',
    'in' => 'La valeur choisie pour :attribute est invalide.',
    'integer' => 'Le champ :attribute doit être un nombre entier.',
    'max' => [
        'array' => 'Le champ :attribute ne peut pas contenir plus de :max éléments.',
        'file' => 'Le fichier :attribute ne peut pas dépasser :max kilo-octets.',
        'numeric' => 'Le champ :attribute ne peut pas être supérieur à :max.',
        'string' => 'Le champ :attribute ne peut pas dépasser :max caractères.',
    ],
    'mimes' => 'Le champ :attribute doit être un fichier de type : :values.',
    'mimetypes' => 'Le champ :attribute doit être un fichier de type : :values.',
    'min' => [
        'array' => 'Le champ :attribute doit contenir au moins :min éléments.',
        'file' => 'Le fichier :attribute doit faire au moins :min kilo-octets.',
        'numeric' => 'Le champ :attribute doit être au moins :min.',
        'string' => 'Le champ :attribute doit contenir au moins :min caractères.',
    ],
    'numeric' => 'Le champ :attribute doit être un nombre.',
    'present' => 'Le champ :attribute doit être présent.',
    'prohibited' => 'Le champ :attribute est interdit.',
    'regex' => 'Le format du champ :attribute est invalide.',
    'required' => 'Le champ :attribute est obligatoire.',
    'required_if' => 'Le champ :attribute est obligatoire quand :other vaut :value.',
    'required_with' => 'Le champ :attribute est obligatoire quand :values est renseigné.',
    'same' => 'Les champs :attribute et :other doivent être identiques.',
    'size' => [
        'array' => 'Le champ :attribute doit contenir :size éléments.',
        'file' => 'Le fichier :attribute doit faire :size kilo-octets.',
        'numeric' => 'Le champ :attribute doit valoir :size.',
        'string' => 'Le champ :attribute doit contenir :size caractères.',
    ],
    'string' => 'Le champ :attribute doit être une chaîne de caractères.',
    'unique' => 'Cette valeur de :attribute est déjà utilisée.',
    'uploaded' => 'Le fichier :attribute n’a pas pu être envoyé.',
    'url' => 'Le champ :attribute doit être une URL valide.',

    // Contraintes portées par Illuminate\Validation\Rules\Password.
    'password' => [
        'letters' => 'Le mot de passe doit contenir au moins une lettre.',
        'mixed' => 'Le mot de passe doit contenir au moins une majuscule et une minuscule.',
        'numbers' => 'Le mot de passe doit contenir au moins un chiffre.',
        'symbols' => 'Le mot de passe doit contenir au moins un caractère spécial.',
        'uncompromised' => 'Ce mot de passe est apparu dans une fuite de données. Choisissez-en un autre.',
    ],

    /*
    | Noms lisibles des champs, pour que « Le champ password_confirmation est
    | obligatoire » devienne une phrase compréhensible par un utilisateur.
    */
    'attributes' => [
        'amount' => 'montant',
        'city' => 'ville',
        'comment' => 'commentaire',
        'country' => 'pays',
        'description' => 'description',
        'email' => 'email',
        'first_name' => 'prénom',
        'last_name' => 'nom',
        'password' => 'mot de passe',
        'password_confirmation' => 'confirmation du mot de passe',
        'phone' => 'téléphone',
        'provider' => 'moyen de paiement',
        'rating' => 'note',
        'reason' => 'motif',
        'role' => 'rôle',
        'title' => 'titre',
        'token' => 'jeton',
    ],

];
