<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceType;
use App\Enums\UserRole;
use App\Models\Invoice;
use App\Services\AuditLogService;
use App\Services\Documents\InvoiceDocumentService;
use App\Support\Permissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InvoiceDownloadController extends Controller
{
    public function __construct(
        private readonly InvoiceDocumentService $invoiceDocumentService,
        private readonly AuditLogService $auditLogService,
    ) {}

    public function __invoke(Request $request, Invoice $invoice): StreamedResponse
    {
        $user = $request->user();
        abort_unless($user && $this->canDownload($user, $invoice), 403);

        if (! $invoice->pdf_path || ! Storage::disk('local')->exists($invoice->pdf_path)) {
            $invoice = $this->invoiceDocumentService->regenerate($invoice);
        }

        $this->auditLogService->log(
            'invoice.downloaded',
            $invoice,
            null,
            ['invoice_reference' => $invoice->invoice_reference],
            $user,
        );

        return Storage::disk('local')->download(
            $invoice->pdf_path,
            $invoice->invoice_reference.'.pdf',
        );
    }

    private function canDownload($user, Invoice $invoice): bool
    {
        if ($user->hasRole(UserRole::SuperAdmin->value) || $user->can(Permissions::MANAGE_PAYMENTS)) {
            return true;
        }

        if (in_array($invoice->type, [InvoiceType::BuyerInvoice, InvoiceType::BuyerReceipt], true)) {
            $buyerCompanyId = $user->buyerProfile?->company_id;

            return $buyerCompanyId && $invoice->company_id === $buyerCompanyId;
        }

        if (in_array($invoice->type, [InvoiceType::SellerStatement, InvoiceType::CommissionStatement], true)) {
            if ($invoice->user_id === $user->id) {
                return true;
            }

            $sellerCompanyId = $user->sellerProfile?->company_id;

            return $sellerCompanyId
                && $invoice->company_id === $sellerCompanyId
                && (
                    $user->can(Permissions::VIEW_OWN_COMMISSIONS)
                    || $user->can(Permissions::MANAGE_STAFF_COMMISSIONS)
                );
        }

        return false;
    }
}
