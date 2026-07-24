<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $documentTitle }} {{ $invoice->invoice_reference }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111827; }
        .header { border-bottom: 2px solid #16a34a; padding-bottom: 12px; margin-bottom: 20px; }
        .brand { font-size: 18px; font-weight: bold; color: #0f172a; }
        .muted { color: #6b7280; }
        .mono { font-family: DejaVu Sans Mono, monospace; }
    </style>
</head>
<body>
<div class="header">
    <div class="brand">RML Energy Saving</div>
    <div class="muted">{{ $documentTitle }}</div>
</div>

<p><strong>Statement:</strong> <span class="mono">{{ $invoice->invoice_reference }}</span></p>
<p><strong>Commission:</strong> <span class="mono">{{ $commission->commission_reference }}</span></p>
<p><strong>Lead:</strong> <span class="mono">{{ $lead?->lead_reference }}</span></p>
<p><strong>Issued:</strong> {{ optional($invoice->issued_at)->format('Y-m-d') }}</p>
<p><strong>Status:</strong> {{ $commission->status?->value }}</p>
@if($seller)
    <p><strong>Agent / staff:</strong> {{ $seller->name }}</p>
@endif
@if($company)
    <p class="muted">{{ $company->name }}</p>
@endif
<p><strong>Percentage:</strong> {{ number_format((float) $commission->percentage, 2) }}%</p>
<p><strong>Base amount:</strong> {{ number_format((float) $commission->base_amount, 2) }} EUR</p>
<p><strong>Commission amount:</strong> {{ number_format((float) $commission->commission_amount, 2) }} EUR</p>
<p class="muted">Buyer details and platform margin are not included.</p>
</body>
</html>
