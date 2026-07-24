<x-mail::message>
# Account {{ $status }}

Hello {{ $user->name }},

@if ($status === 'approved')
Your RML Energy Saving account has been approved. You can now sign in and access your portal.
@elseif ($status === 'rejected')
Your registration was not approved.
@if ($reason)
**Reason:** {{ $reason }}
@endif
@elseif ($status === 'suspended')
Your account has been suspended.
@if ($reason)
**Reason:** {{ $reason }}
@endif
@else
Your account status has been updated to **{{ $status }}**.
@endif

<x-mail::button :url="$loginUrl">
Sign in
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
