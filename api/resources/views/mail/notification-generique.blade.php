<x-mail::message>
{!! nl2br(e($corps)) !!}

Cordialement,<br>
{{ config('app.name') }}
</x-mail::message>
