<x-mail::message>
# Bonjour {{ $prenom }},

Votre compte **{{ $profil }}** vient d'être créé sur {{ config('app.name') }}.

Votre identifiant de connexion est votre adresse : **{{ $email }}**

Pour choisir votre mot de passe, cliquez sur le bouton ci-dessous et indiquez cette adresse : vous recevrez un code à six chiffres.

<x-mail::button :url="$lien">
Choisir mon mot de passe
</x-mail::button>

Personne, pas même notre équipe, ne connaît ni ne vous demandera votre mot de passe.

Cordialement,<br>
{{ config('app.name') }}
</x-mail::message>
