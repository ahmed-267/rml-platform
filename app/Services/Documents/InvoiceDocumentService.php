<?php

namespace App\Services\Documents;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\Commission;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Payout;
use App\Models\Purchase;
use App\Services\AuditLogService;
use App\Support\FilesystemDisk;
use App\Support\ReferenceGenerator;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class InvoiceDocumentService
{
    public function __construct(
        private readonly AuditLogService $auditLogService = new AuditLogService,
    ) {}

    public function ensureBuyerInvoice(Purchase $purchase, Payment $payment): Invoice
    {
        $existing = Invoice::query()
            ->where('purchase_id', $purchase->id)
            ->where('type', InvoiceType::BuyerInvoice->value)
            ->first();

        if ($existing) {
            return $this->regeneratePdf($existing, $purchase, $payment);
        }

        $invoice = Invoice::query()->create([
            'invoice_reference' => ReferenceGenerator::invoice(),
            'payment_id' => $payment->id,
            'purchase_id' => $purchase->id,
            'company_id' => $purchase->buyer_company_id,
            'user_id' => $purchase->buyer_user_id,
            'type' => InvoiceType::BuyerInvoice,
            'status' => InvoiceStatus::Issued,
            'subtotal' => $payment->amount,
            'tax_amount' => 0,
            'total' => $payment->amount,
            'currency' => $payment->currency ?: 'EUR',
            'issued_at' => now(),
        ]);

        return $this->regeneratePdf($invoice, $purchase, $payment);
    }

    public function ensureBuyerReceipt(Purchase $purchase, Payment $payment): Invoice
    {
        $existing = Invoice::query()
            ->where('purchase_id', $purchase->id)
            ->where('type', InvoiceType::BuyerReceipt->value)
            ->first();

        if ($existing) {
            $existing->update(['status' => InvoiceStatus::Paid]);

            return $this->regeneratePdf($existing, $purchase, $payment);
        }

        $invoice = Invoice::query()->create([
            'invoice_reference' => ReferenceGenerator::invoice(),
            'payment_id' => $payment->id,
            'purchase_id' => $purchase->id,
            'company_id' => $purchase->buyer_company_id,
            'user_id' => $purchase->buyer_user_id,
            'type' => InvoiceType::BuyerReceipt,
            'status' => InvoiceStatus::Paid,
            'subtotal' => $payment->amount,
            'tax_amount' => 0,
            'total' => $payment->amount,
            'currency' => $payment->currency ?: 'EUR',
            'issued_at' => now(),
        ]);

        return $this->regeneratePdf($invoice, $purchase, $payment);
    }

    public function ensureSellerPayoutStatement(Payout $payout): Invoice
    {
        $existing = Invoice::query()
            ->where('type', InvoiceType::SellerStatement->value)
            ->where('company_id', $payout->seller_company_id)
            ->where('user_id', $payout->seller_user_id)
            ->get()
            ->first(fn (Invoice $invoice) => str_contains((string) $invoice->pdf_path, $payout->payout_reference)
                || str_contains((string) $invoice->invoice_reference, $payout->payout_reference));

        if ($existing) {
            return $this->regeneratePayoutPdf($existing, $payout);
        }

        $invoice = Invoice::query()->create([
            'invoice_reference' => ReferenceGenerator::invoice(),
            'payment_id' => $payout->payment_id,
            'purchase_id' => null,
            'company_id' => $payout->seller_company_id,
            'user_id' => $payout->seller_user_id,
            'type' => InvoiceType::SellerStatement,
            'status' => InvoiceStatus::Issued,
            'subtotal' => $payout->amount,
            'tax_amount' => 0,
            'total' => $payout->amount,
            'currency' => $payout->currency ?: 'EUR',
            'issued_at' => now(),
        ]);

        return $this->regeneratePayoutPdf($invoice, $payout);
    }

    public function ensureCommissionStatement(Commission $commission): Invoice
    {
        $byPath = Invoice::query()
            ->where('type', InvoiceType::CommissionStatement->value)
            ->where('user_id', $commission->seller_user_id)
            ->get()
            ->first(fn (Invoice $invoice) => str_contains((string) $invoice->pdf_path, $commission->commission_reference));

        if ($byPath) {
            return $this->regenerateCommissionPdf($byPath, $commission);
        }

        $invoice = Invoice::query()->create([
            'invoice_reference' => ReferenceGenerator::invoice(),
            'payment_id' => null,
            'purchase_id' => null,
            'company_id' => $commission->seller_company_id,
            'user_id' => $commission->seller_user_id,
            'type' => InvoiceType::CommissionStatement,
            'status' => InvoiceStatus::Issued,
            'subtotal' => $commission->commission_amount,
            'tax_amount' => 0,
            'total' => $commission->commission_amount,
            'currency' => 'EUR',
            'issued_at' => now(),
        ]);

        return $this->regenerateCommissionPdf($invoice, $commission);
    }

    public function regenerate(Invoice $invoice): Invoice
    {
        $invoice->loadMissing(['payment', 'purchase.items.lead', 'company', 'user']);

        return match ($invoice->type) {
            InvoiceType::SellerStatement => $this->regeneratePayoutPdf(
                $invoice,
                Payout::query()
                    ->where('seller_company_id', $invoice->company_id)
                    ->where('seller_user_id', $invoice->user_id)
                    ->latest('id')
                    ->firstOrFail()
            ),
            InvoiceType::CommissionStatement => $this->regenerateCommissionPdf(
                $invoice,
                Commission::query()
                    ->where('seller_user_id', $invoice->user_id)
                    ->where('commission_amount', $invoice->total)
                    ->latest('id')
                    ->firstOrFail()
            ),
            default => $this->regeneratePdf(
                $invoice,
                $invoice->purchase ?? Purchase::query()->findOrFail($invoice->purchase_id),
                $invoice->payment ?? Payment::query()->findOrFail($invoice->payment_id),
            ),
        };
    }

    private function regeneratePdf(Invoice $invoice, Purchase $purchase, Payment $payment): Invoice
    {
        $purchase->loadMissing(['items.lead.scheme', 'items.lead.zone', 'buyerCompany', 'buyerUser']);

        $lines = $purchase->items->map(function ($item) {
            $lead = $item->lead;

            return [
                'reference' => $lead?->lead_reference ?? '—',
                'description' => trim(($lead?->scheme?->name ?? 'Lead').' / '.($lead?->zone?->code ?? '—')),
                'size_m2' => $lead?->size_m2,
                'unit_price' => $item->unit_price,
                'total' => $item->total_price,
            ];
        })->all();

        $view = $invoice->type === InvoiceType::BuyerReceipt ? 'pdf.buyer-receipt' : 'pdf.buyer-invoice';

        $path = sprintf(
            'invoices/%s/%s.pdf',
            $invoice->type->value,
            $invoice->invoice_reference
        );

        $this->storePdf($path, $view, [
            'invoice' => $invoice,
            'payment' => $payment,
            'purchase' => $purchase,
            'company' => $purchase->buyerCompany,
            'buyer' => $purchase->buyerUser,
            'lines' => $lines,
            'documentTitle' => $invoice->type === InvoiceType::BuyerReceipt ? 'Receipt' : 'Invoice',
        ]);

        $invoice->update(['pdf_path' => $path]);

        $this->auditLogService->log(
            'invoice.pdf_generated',
            $invoice,
            null,
            ['pdf_path' => $path, 'type' => $invoice->type->value],
        );

        return $invoice->fresh() ?? $invoice;
    }

    private function regeneratePayoutPdf(Invoice $invoice, Payout $payout): Invoice
    {
        $payout->loadMissing(['sellerCompany', 'sellerUser']);

        $path = sprintf(
            'invoices/seller_statement/%s-%s.pdf',
            $invoice->invoice_reference,
            $payout->payout_reference
        );

        $this->storePdf($path, 'pdf.seller-payout-statement', [
            'invoice' => $invoice,
            'payout' => $payout,
            'company' => $payout->sellerCompany,
            'seller' => $payout->sellerUser,
            'documentTitle' => 'Payout Statement',
        ]);

        $invoice->update(['pdf_path' => $path]);

        $this->auditLogService->log(
            'invoice.pdf_generated',
            $invoice,
            null,
            ['pdf_path' => $path, 'type' => $invoice->type->value, 'payout' => $payout->payout_reference],
        );

        return $invoice->fresh() ?? $invoice;
    }

    private function regenerateCommissionPdf(Invoice $invoice, Commission $commission): Invoice
    {
        $commission->loadMissing(['sellerCompany', 'sellerUser', 'lead']);

        $path = sprintf(
            'invoices/commission_statement/%s-%s.pdf',
            $invoice->invoice_reference,
            $commission->commission_reference
        );

        $this->storePdf($path, 'pdf.commission-statement', [
            'invoice' => $invoice,
            'commission' => $commission,
            'company' => $commission->sellerCompany,
            'seller' => $commission->sellerUser,
            'lead' => $commission->lead,
            'documentTitle' => 'Commission Statement',
        ]);

        $invoice->update(['pdf_path' => $path]);

        $this->auditLogService->log(
            'invoice.pdf_generated',
            $invoice,
            null,
            ['pdf_path' => $path, 'type' => $invoice->type->value, 'commission' => $commission->commission_reference],
        );

        return $invoice->fresh() ?? $invoice;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function storePdf(string $path, string $view, array $data): void
    {
        $disk = FilesystemDisk::uploads();

        if (app()->environment('testing')) {
            Storage::disk($disk)->put($path, "%PDF-1.4\n% RML test stub for {$data['invoice']->invoice_reference}\n");

            return;
        }

        $pdf = Pdf::loadView($view, $data);
        Storage::disk($disk)->put($path, $pdf->output());
    }
}
