<x-mail::message>
# Solde de stickers FNE bas

Le solde de stickers FNE (DGI) est descendu à **{{ $solde }}**, sous le seuil d'alerte de {{ $seuil }}.

Sans sticker disponible, la transmission des prochaines factures à la DGI sera refusée. Commandez un réapprovisionnement auprès de la DGI dès que possible.

Cordialement,<br>
{{ config('app.name') }}
</x-mail::message>
