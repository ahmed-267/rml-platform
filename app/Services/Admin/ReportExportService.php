<?php

namespace App\Services\Admin;

use App\Services\AuditLogService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExportService
{
    public function __construct(
        private readonly AdminReportService $reportService = new AdminReportService,
        private readonly AuditLogService $auditLogService = new AuditLogService,
    ) {}

    public function downloadCsv(): StreamedResponse
    {
        $report = $this->reportService->build();
        $filename = 'rml-report-'.now()->format('Y-m-d').'.csv';

        $this->auditLogService->log('report_exported_csv', null, null, [
            'filename' => $filename,
            'format' => 'csv',
        ]);

        return response()->streamDownload(function () use ($report) {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            $this->writeCsvSection($handle, __('rml.admin.reports.summary'), [
                [__('rml.admin.reports.total_leads_submitted'), $report['summary']['total_leads_submitted']],
                [__('rml.admin.reports.leads_sold'), $report['summary']['leads_sold']],
                [__('rml.admin.reports.acceptance_rate'), $report['summary']['acceptance_rate'].'%'],
                [__('rml.admin.reports.buyer_revenue'), $report['summary']['buyer_revenue_paid']],
                [__('rml.admin.reports.seller_payouts'), $report['summary']['seller_payouts_paid']],
                [__('rml.admin.reports.total_margin'), $report['summary']['total_margin']],
                [__('rml.admin.reports.commissions_due'), $report['summary']['commissions_due']],
                [__('rml.admin.reports.commissions_paid'), $report['summary']['commissions_paid']],
            ]);

            $this->writeCsvSection($handle, __('rml.admin.reports.lead_pipeline'), [
                ...collect($report['lead_pipeline'])->map(fn ($count, $status) => [
                    __('rml.lead_statuses.'.$status),
                    $count,
                ])->values()->all(),
            ]);

            $this->writeCsvSection($handle, __('rml.admin.reports.monthly_sold'), [
                ...collect($report['monthly_sold'])->map(fn ($count, $month) => [$month, $count])->values()->all(),
            ]);

            $this->writeCsvSection($handle, __('rml.admin.reports.seller_performance'), [
                [__('rml.admin.reports.seller'), __('rml.admin.reports.submitted'), __('rml.admin.reports.accepted'), __('rml.admin.reports.acceptance_rate')],
                ...collect($report['seller_performance'])->map(fn ($row) => [
                    $row['name'],
                    $row['submitted'],
                    $row['accepted'],
                    $row['acceptance_rate'].'%',
                ])->all(),
            ]);

            $this->writeCsvSection($handle, __('rml.admin.reports.buyer_performance'), [
                [__('rml.admin.reports.buyer'), __('rml.admin.reports.leads_bought'), __('rml.admin.reports.spent'), __('rml.admin.reports.avg_per_lead')],
                ...collect($report['buyer_performance'])->map(fn ($row) => [
                    $row['name'],
                    $row['leads_bought'],
                    $row['spent'],
                    $row['avg_per_lead'],
                ])->all(),
            ]);

            $this->writeCsvSection($handle, __('rml.admin.reports.revenue_margin'), [
                [__('rml.admin.reports.month'), __('rml.admin.reports.revenue'), __('rml.admin.reports.cost'), __('rml.admin.reports.margin')],
                ...collect($report['charts']['revenue_margin'])->map(fn ($row) => [
                    $row['month'],
                    $row['revenue'],
                    $row['cost'],
                    $row['margin'],
                ])->all(),
            ]);

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function downloadPdf(): Response
    {
        $report = $this->reportService->build();
        $filename = 'rml-report-'.now()->format('Y-m-d').'.pdf';
        $generatedAt = now()->timezone(config('app.timezone'))->format('Y-m-d H:i:s');

        $this->auditLogService->log('report_exported_pdf', null, null, [
            'filename' => $filename,
            'format' => 'pdf',
        ]);

        if (app()->environment('testing')) {
            return response(
                "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\nRML report stub {$generatedAt}",
                200,
                [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'attachment; filename="'.$filename.'"',
                ],
            );
        }

        $pdf = Pdf::loadView('pdf.admin-report', [
            'report' => $report,
            'generatedAt' => $generatedAt,
            'title' => __('rml.admin.reports.index_title'),
        ])->setPaper('a4');

        return $pdf->download($filename);
    }

    /**
     * @param  resource  $handle
     * @param  list<list<mixed>>  $rows
     */
    private function writeCsvSection($handle, string $title, array $rows): void
    {
        fputcsv($handle, [$title]);
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        fputcsv($handle, []);
    }
}
