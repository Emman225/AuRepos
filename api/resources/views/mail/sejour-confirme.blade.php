<x-mail::message>
# Bonjour {{ $prenom }},

Votre séjour **{{ $reference }}** est confirmé.

**{{ $logement }}**<br>
Du {{ $arrivee }} au {{ $depart }}

## Votre code d'arrivée

<x-mail::panel>
<div style="font-size: 28px; letter-spacing: 6px; font-weight: bold; text-align: center;">{{ $code }}</div>
</x-mail::panel>

À votre arrivée, remettez ce code à l'agent d'accueil : c'est lui qui ouvre votre séjour. **Ne le communiquez à personne d'autre**, et surtout pas avant d'être sur place. Notre équipe ne vous le demandera jamais par téléphone.

@if ($adresse || $repere)
## Adresse

{{ $adresse }}@if ($repere)<br>Repère : {{ $repere }}@endif
@endif

@if ($consignes)
## Consignes d'accès

{{ $consignes }}
@endif

<x-mail::button :url="$lien">
Ouvrir mon espace
</x-mail::button>

Vous retrouvez ce code à tout moment dans votre espace, rubrique « Mes séjours ».

Cordialement,<br>
{{ config('app.name') }}
</x-mail::message>
