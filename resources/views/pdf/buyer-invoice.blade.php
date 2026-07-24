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
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { border-bottom: 1px solid #e5e7eb; padding: 8px; text-align: left; }
        th { background: #f8fafc; }
        .totals { margin-top: 16px; text-align: right; }
        .mono { font-family: DejaVu Sans Mono, monospace; }
    </style>
</head>
<body>
<div class="header">
    <div class="brand">RML Energy Exchange</div>
    <div class="muted">{{ $documentTitle }}</div>
</div>

<p><strong>Reference:</strong> <span class="mono">{{ $invoice->invoice_reference }}</span></p>
<p><strong>Payment:</strong> <span class="mono">{{ $payment->payment_reference }}</span></p>
<p><strong>Purchase:</strong> <span class="mono">{{ $purchase->purchase_reference }}</span></p>
<p><strong>Issued:</strong> {{ optional($invoice->issued_at)->format('Y-m-d') }}</p>
<p><strong>Status:</strong> {{ $invoice->status?->value }}</p>
<p><strong>Payment method:</strong> {{ $payment->method?->value }}</p>
<p><strong>Payment status:</strong> {{ $payment->status?->value }}</p>

@if($company)
    <p><strong>Bill to:</strong> {{ $company->name }}</p>
@endif
@if($buyer)
    <p class="muted">{{ $buyer->name }} · {{ $buyer->email }}</p>
@endif

<table>
    <thead>
    <tr>
        <th>Reference</th>
        <th>Description</th>
        <th>Size m²</th>
        <th>Unit</th>
        <th>Total</th>
    </tr>
    </thead>
    <tbody>
    @foreach($lines as $line)
        <tr>
            <td class="mono">{{ $line['reference'] }}</td>
            <td>{{ $line['description'] }}</td>
            <td>{{ $line['size_m2'] ?? '—' }}</td>
            <td>{{ number_format((float) $line['unit_price'], 2) }}</td>
            <td>{{ number_format((float) $line['total'], 2) }}</td>
        </tr>
    @endforeach
    </tbody>
</table>

<div class="totals">
    <p>Subtotal: {{ number_format((float) $invoice->subtotal, 2) }} {{ $invoice->currency }}</p>
    <p>Tax: {{ number_format((float) ($invoice->tax_amount ?? 0), 2) }} {{ $invoice->currency }}</p>
    <p><strong>Total: {{ number_format((float) $invoice->total, 2) }} {{ $invoice->currency }}</strong></p>
</div>
</body>
</html>
