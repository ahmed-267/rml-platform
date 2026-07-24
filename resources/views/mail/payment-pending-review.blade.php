<x-mail::message>
# Payment pending review

Manual bank transfer payment **{{ $reference }}** for **{{ number_format((float) $amount, 2) }} EUR** is awaiting confirmation.

<x-mail::button :url="$adminUrl">
Review payments
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
