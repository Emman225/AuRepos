<x-mail::message>
# Bonjour {{ $prenom }},

Votre compte **{{ $profil }}** vient d'être créé sur {{ config('app.name') }}.

Votre identifiant de connexion :

<x-mail::panel>
<div style="font-size: 22px; letter-spacing: 2px; font-weight: bold; text-align: center;">{{ $identifiant }}</div>
</x-mail::panel>

Pour choisir votre mot de passe, cliquez sur le bouton ci-dessous et indiquez cet identifiant : vous recevrez un code à six chiffres.

<x-mail::button :url="$lien">
Choisir mon mot de passe
</x-mail::button>

Personne, pas même notre équipe, ne connaît ni ne vous demandera votre mot de passe.

Cordialement,<br>
{{ config('app.name') }}
</x-mail::message>
