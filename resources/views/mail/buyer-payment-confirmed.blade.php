<x-mail::message>
# Payment confirmed

Payment **{{ $reference }}** has been confirmed for **{{ number_format((float) $amount, 2) }} EUR**.

Your purchased lead details are now available in the buyer portal.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
