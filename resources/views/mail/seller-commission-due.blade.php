<x-mail::message>
# Commission due

Commission **{{ $reference }}** for **{{ number_format((float) $amount, 2) }} EUR** is now due.

Buyer details and platform margin are not included.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
