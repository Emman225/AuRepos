<x-mail::message>
# Bonjour {{ $prenom }},

{{ $phrase }}

<x-mail::panel>
<div style="font-size: 28px; letter-spacing: 6px; font-weight: bold; text-align: center;">{{ $code }}</div>
</x-mail::panel>

Ce code est valable {{ $minutes }} minutes et ne sert qu'une fois.

Si vous n'êtes pas à l'origine de cette demande, ignorez ce message : votre compte ne change pas.

Cordialement,<br>
{{ config('app.name') }}
</x-mail::message>
