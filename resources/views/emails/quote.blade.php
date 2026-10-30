<x-mail::message>
{!! nl2br(e($body)) !!}

Saludos,<br>
**{{ $company['name'] }}**<br>
{{ collect([$company['email'], $company['phone'], $company['website']])->filter()->join(' · ') }}
</x-mail::message>
