<x-mail::message>
# Seller staff invitation

You have been invited to join **{{ $companyName }}** on {{ config('app.name') }} as seller staff.

@if ($expiresAt)
This invitation expires on **{{ $expiresAt }}**.
@endif

<x-mail::button :url="$acceptUrl">
Accept invitation
</x-mail::button>

After you create your account, a Super Admin must approve it before you can access the seller portal.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
