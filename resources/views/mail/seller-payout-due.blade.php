<x-mail::message>
# Payout due

A seller payout **{{ $reference }}** for **{{ number_format((float) $amount, 2) }} EUR** is now due.

Buyer details are not included in this notification.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
