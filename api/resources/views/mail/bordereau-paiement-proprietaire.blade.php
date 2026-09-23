<x-mail::message>
# Bonjour {{ $prenom }},

Votre paiement de **{{ $net }} F** a été effectué.

Votre bordereau est joint à ce message.

<x-mail::button :url="$lien">
Ouvrir mon espace
</x-mail::button>

Cordialement,<br>
{{ config('app.name') }}
</x-mail::message>
