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
    <div class="brand">RML Energy Exchange</div>
    <div class="muted">{{ $documentTitle }}</div>
</div>

<p><strong>Statement:</strong> <span class="mono">{{ $invoice->invoice_reference }}</span></p>
<p><strong>Payout:</strong> <span class="mono">{{ $payout->payout_reference }}</span></p>
<p><strong>Issued:</strong> {{ optional($invoice->issued_at)->format('Y-m-d') }}</p>
<p><strong>Status:</strong> {{ $payout->status?->value }}</p>
@if($company)
    <p><strong>Seller company:</strong> {{ $company->name }}</p>
@endif
@if($seller)
    <p class="muted">{{ $seller->name }}</p>
@endif
<p><strong>Amount due:</strong> {{ number_format((float) $payout->amount, 2) }} {{ $payout->currency }}</p>
<p class="muted">{{ $payout->notes }}</p>
<p class="muted">Buyer details are not included on seller statements.</p>
</body>
</html>
