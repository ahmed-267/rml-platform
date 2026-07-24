<x-mail::message>
# New {{ ucfirst($registrationType) }} registration

**{{ $registrant->name }}** ({{ $registrant->email }}) has submitted a {{ $registrationType }} registration and is waiting for Super Admin approval.

<x-mail::button :url="$approvalsUrl">
Review approvals
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
