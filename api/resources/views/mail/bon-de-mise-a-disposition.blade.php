<x-mail::message>
# Bonjour {{ $prenom }},

Un séjour vient d'être confirmé dans votre logement **{{ $logement }}**.

Du {{ $arrivee }} au {{ $depart }} — {{ $nuitees }} nuitée(s).

Le bon de mise à disposition est disponible dans votre espace. Votre reversement portera sur les nuitées réellement consommées.

<x-mail::button :url="$lien">
Ouvrir mon espace propriétaire
</x-mail::button>

Cordialement,<br>
{{ config('app.name') }}
</x-mail::message>
