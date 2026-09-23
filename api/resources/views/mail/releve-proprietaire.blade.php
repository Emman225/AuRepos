<x-mail::message>
# Bonjour {{ $prenom }},

Votre relevé de **{{ $periode }}** est disponible : {{ $nuitees }} nuitée(s) consommée(s), net à reverser **{{ $net }} F**.

Le relevé et votre attestation de retenue à la source du mois sont joints à ce message. Vous les retrouvez à tout moment dans votre espace, rubrique « Relevés ».

<x-mail::button :url="$lien">
Ouvrir mon espace
</x-mail::button>

Cordialement,<br>
{{ config('app.name') }}
</x-mail::message>
