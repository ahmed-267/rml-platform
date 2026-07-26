<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Services\Admin\AdminReportService;
use App\Services\Admin\ReportExportService;
use App\Support\Permissions;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(
        private readonly AdminReportService $reportService,
        private readonly ReportExportService $exportService,
    ) {}

    public function index(Request $request): InertiaResponse
    {
        $this->authorizeReports($request);

        return Inertia::render('Admin/Reports/Index', $this->reportService->build(
            $this->filtersFromRequest($request),
        ));
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $this->authorizeReports($request);

        return $this->exportService->downloadCsv($this->filtersFromRequest($request));
    }

    public function exportPdf(Request $request): Response
    {
        $this->authorizeReports($request);

        return $this->exportService->downloadPdf($this->filtersFromRequest($request));
    }

    /**
     * @return array<string, mixed>
     */
    private function filtersFromRequest(Request $request): array
    {
        return [
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
            'scheme_id' => $request->input('scheme_id'),
            'tab' => $request->input('tab'),
        ];
    }

    private function authorizeReports(Request $request): void
    {
        abort_unless(
            $request->user()?->can(Permissions::VIEW_REPORTS)
                || $request->user()?->hasRole(UserRole::SuperAdmin->value)
                || $request->user()?->hasRole(UserRole::AdminStaff->value),
            403,
        );
    }
}
