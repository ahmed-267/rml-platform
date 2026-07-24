<x-mail::message>
# Payout paid

Seller payout **{{ $reference }}** for **{{ number_format((float) $amount, 2) }} EUR** has been marked as paid.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
