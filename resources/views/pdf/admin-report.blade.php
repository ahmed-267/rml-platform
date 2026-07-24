<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111827; }
        h1 { font-size: 18px; margin: 0 0 4px; color: #0f172a; }
        h2 { font-size: 13px; margin: 18px 0 8px; color: #0f172a; border-bottom: 1px solid #e5e7eb; padding-bottom: 4px; }
        .muted { color: #6b7280; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        th, td { border-bottom: 1px solid #e5e7eb; padding: 6px 4px; text-align: left; }
        th { background: #f8fafc; font-weight: bold; }
        .kpi { width: 100%; margin-bottom: 8px; }
        .kpi td { border: none; padding: 4px 8px 4px 0; }
        .brand { color: #16a34a; font-weight: bold; font-size: 12px; margin-bottom: 8px; }
    </style>
</head>
<body>
    <div class="brand">RML</div>
    <h1>{{ $title }}</h1>
    <div class="muted">{{ __('rml.admin.reports.generated_at') }}: {{ $generatedAt }}</div>

    <h2>{{ __('rml.admin.reports.summary') }}</h2>
    <table class="kpi">
        <tr>
            <td>{{ __('rml.admin.reports.total_leads_submitted') }}: <strong>{{ $report['summary']['total_leads_submitted'] }}</strong></td>
            <td>{{ __('rml.admin.reports.leads_sold') }}: <strong>{{ $report['summary']['leads_sold'] }}</strong></td>
        </tr>
        <tr>
            <td>{{ __('rml.admin.reports.acceptance_rate') }}: <strong>{{ $report['summary']['acceptance_rate'] }}%</strong></td>
            <td>{{ __('rml.admin.reports.total_margin') }}: <strong>{{ number_format((float) $report['summary']['total_margin'], 2) }}</strong></td>
        </tr>
        <tr>
            <td>{{ __('rml.admin.reports.buyer_revenue') }}: <strong>{{ number_format((float) $report['summary']['buyer_revenue_paid'], 2) }}</strong></td>
            <td>{{ __('rml.admin.reports.seller_payouts') }}: <strong>{{ number_format((float) $report['summary']['seller_payouts_paid'], 2) }}</strong></td>
        </tr>
    </table>

    <h2>{{ __('rml.admin.reports.lead_pipeline') }}</h2>
    <table>
        <thead>
            <tr>
                <th>{{ __('rml.admin.common.status') }}</th>
                <th>{{ __('rml.admin.reports.count') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach($report['lead_pipeline'] as $status => $count)
                <tr>
                    <td>{{ __('rml.lead_statuses.'.$status) }}</td>
                    <td>{{ $count }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h2>{{ __('rml.admin.reports.revenue_margin') }}</h2>
    <table>
        <thead>
            <tr>
                <th>{{ __('rml.admin.reports.month') }}</th>
                <th>{{ __('rml.admin.reports.revenue') }}</th>
                <th>{{ __('rml.admin.reports.cost') }}</th>
                <th>{{ __('rml.admin.reports.margin') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach($report['charts']['revenue_margin'] as $row)
                <tr>
                    <td>{{ $row['month'] }}</td>
                    <td>{{ number_format((float) $row['revenue'], 2) }}</td>
                    <td>{{ number_format((float) $row['cost'], 2) }}</td>
                    <td>{{ number_format((float) $row['margin'], 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h2>{{ __('rml.admin.reports.seller_performance') }}</h2>
    <table>
        <thead>
            <tr>
                <th>{{ __('rml.admin.reports.seller') }}</th>
                <th>{{ __('rml.admin.reports.submitted') }}</th>
                <th>{{ __('rml.admin.reports.accepted') }}</th>
                <th>{{ __('rml.admin.reports.acceptance_rate') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach($report['seller_performance'] as $row)
                <tr>
                    <td>{{ $row['name'] }}</td>
                    <td>{{ $row['submitted'] }}</td>
                    <td>{{ $row['accepted'] }}</td>
                    <td>{{ $row['acceptance_rate'] }}%</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h2>{{ __('rml.admin.reports.buyer_performance') }}</h2>
    <table>
        <thead>
            <tr>
                <th>{{ __('rml.admin.reports.buyer') }}</th>
                <th>{{ __('rml.admin.reports.leads_bought') }}</th>
                <th>{{ __('rml.admin.reports.spent') }}</th>
                <th>{{ __('rml.admin.reports.avg_per_lead') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach($report['buyer_performance'] as $row)
                <tr>
                    <td>{{ $row['name'] }}</td>
                    <td>{{ $row['leads_bought'] }}</td>
                    <td>{{ number_format((float) $row['spent'], 2) }}</td>
                    <td>{{ number_format((float) $row['avg_per_lead'], 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h2>{{ __('rml.admin.reports.monthly_sold') }}</h2>
    <table>
        <thead>
            <tr>
                <th>{{ __('rml.admin.reports.month') }}</th>
                <th>{{ __('rml.admin.reports.count') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach($report['monthly_sold'] as $month => $count)
                <tr>
                    <td>{{ $month }}</td>
                    <td>{{ $count }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
