<x-mail::message>
# Bonjour {{ $prenom }},

Nous avons bien reçu votre règlement de **{{ $montant }} F**.

Votre reçu **{{ $numero }}** est joint à ce message. Vous le retrouvez à tout moment dans votre espace, rubrique « Mes paiements ».

<x-mail::button :url="$lien">
Ouvrir mon espace
</x-mail::button>

Cordialement,<br>
{{ config('app.name') }}
</x-mail::message>
