<x-mail::message>
# Payment created

Your payment **{{ $reference }}** has been created for **{{ number_format((float) $amount, 2) }} EUR**.

Method: {{ $method }}

Lead details are released only after payment is confirmed.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
