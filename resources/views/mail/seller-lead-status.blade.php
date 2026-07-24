<x-mail::message>
# {{ $brand }}

@if ($event === 'accepted')
## {{ __('rml.mail.lead_accepted_heading') }}

{{ __('rml.mail.lead_accepted_body', ['reference' => $reference, 'status' => $statusLabel]) }}
@elseif ($event === 'rejected')
## {{ __('rml.mail.lead_rejected_heading') }}

{{ __('rml.mail.lead_rejected_body', ['reference' => $reference, 'status' => $statusLabel]) }}

@if ($details)
**{{ __('rml.mail.reason_label') }}:** {{ $details }}
@endif
@else
## {{ __('rml.mail.lead_info_requested_heading') }}

{{ __('rml.mail.lead_info_requested_body', ['reference' => $reference, 'status' => $statusLabel]) }}

@if ($details)
**{{ __('rml.mail.details_label') }}:** {{ $details }}
@endif
@endif

<x-mail::button :url="$leadUrl">
{{ __('rml.mail.view_lead_button') }}
</x-mail::button>

{{ __('rml.mail.seller_privacy_note') }}

Thanks,<br>
{{ $brand }}
</x-mail::message>
