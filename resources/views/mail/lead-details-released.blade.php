<x-mail::message>
# Lead details released

Payment **{{ $reference }}** is confirmed and customer details for your purchase are now available.

<x-mail::button :url="$purchasesUrl">
View purchases
</x-mail::button>

Seller information is never shared with buyers.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
