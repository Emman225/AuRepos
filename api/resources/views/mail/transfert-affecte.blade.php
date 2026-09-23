<x-mail::message>
# Bonjour {{ $prenom }},

Votre transfert **{{ $reference }}** est organisé : un chauffeur viendra vous chercher à **{{ $lieu }}**, le {{ $date_heure }}.

## Votre code de prise en charge

<x-mail::panel>
<div style="font-size: 28px; letter-spacing: 6px; font-weight: bold; text-align: center;">{{ $code }}</div>
</x-mail::panel>

À son arrivée, remettez ce code au chauffeur : c'est lui qui clôture votre transfert. **Ne le communiquez à personne d'autre**, et surtout pas avant qu'il ne soit sur place. Notre équipe ne vous le demandera jamais par téléphone.

<x-mail::button :url="$lien">
Ouvrir mon espace
</x-mail::button>

Vous retrouvez ce code à tout moment dans votre espace, rubrique « Mes extras et transferts ».

Cordialement,<br>
{{ config('app.name') }}
</x-mail::message>
