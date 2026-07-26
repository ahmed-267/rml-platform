<?php

namespace App\Http\Controllers\Seller;

use App\Enums\CommissionStatus;
use App\Enums\InvoiceType;
use App\Enums\PayoutStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Seller\UpdateSellerBankRequest;
use App\Models\Commission;
use App\Models\Invoice;
use App\Models\Payout;
use App\Services\LeadPricingService;
use App\Support\ListPagination;
use App\Support\ListSort;
use App\Support\Permissions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PaymentController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $profile = $user->sellerProfile;
        $companyId = $profile?->company_id;
        $canViewCompany = $companyId && $user->can(Permissions::VIEW_COMPANY_LEADS);

        $commissionsQuery = $this->commissionsQuery($user->id, $canViewCompany ? $companyId : null);
        $payoutsQuery = $this->payoutsQuery($user->id, $canViewCompany ? $companyId : null);

        $tab = $request->string('tab')->toString();
        if (! in_array($tab, ['commission', 'history'], true)) {
            $tab = 'commission';
        }

        $summaries = [
            'pending_payout' => (float) (clone $payoutsQuery)->where('status', PayoutStatus::Pending->value)->sum('amount'),
            'paid_out' => (float) (clone $payoutsQuery)->where('status', PayoutStatus::Paid->value)->sum('amount'),
            'commission_due' => (float) (clone $commissionsQuery)->where('status', CommissionStatus::Due->value)->sum('commission_amount'),
            'commission_paid' => (float) (clone $commissionsQuery)->where('status', CommissionStatus::Paid->value)->sum('commission_amount'),
        ];

        $commissionSortRequest = $this->prefixedSortRequest($request, 'commission');
        $commissionSort = ListSort::apply(
            $commissionsQuery,
            $commissionSortRequest,
            [
                'reference' => 'commission_reference',
                'status' => 'status',
                'amount' => 'commission_amount',
                'date' => 'due_at',
            ],
            'date',
            'desc',
        );
        $commissionPerPage = ListPagination::perPage($commissionSortRequest);

        $commissions = $commissionsQuery
            ->paginate($commissionPerPage, ['*'], 'commissions_page')
            ->withQueryString()
            ->through(fn (Commission $c) => $this->transformCommission($c));

        $payoutSortRequest = $this->prefixedSortRequest($request, 'payout');
        $payoutSort = ListSort::apply(
            $payoutsQuery,
            $payoutSortRequest,
            [
                'reference' => 'payout_reference',
                'status' => 'status',
                'amount' => 'amount',
                'date' => 'due_date',
            ],
            'date',
            'desc',
        );
        $payoutPerPage = ListPagination::perPage($payoutSortRequest);

        $payouts = $payoutsQuery
            ->paginate($payoutPerPage, ['*'], 'payouts_page')
            ->withQueryString()
            ->through(fn (Payout $p) => $this->transformPayout($p));

        return Inertia::render('Seller/Payments', [
            'tab' => $tab,
            'bank_details' => [
                'bank_account_iban' => $profile?->bank_account_iban,
                'bank_account_name' => $profile?->bank_account_name,
                'payout_method' => $profile?->payout_method,
            ],
            'commissions' => $commissions,
            'payouts' => $payouts,
            'summaries' => $summaries,
            'filters' => [
                'commission_sort' => $commissionSort['sort'],
                'commission_direction' => $commissionSort['direction'],
                'commission_per_page' => $commissionPerPage,
                'payout_sort' => $payoutSort['sort'],
                'payout_direction' => $payoutSort['direction'],
                'payout_per_page' => $payoutPerPage,
            ],
        ]);
    }

    public function updateBankDetails(UpdateSellerBankRequest $request): RedirectResponse
    {
        $profile = $request->user()->sellerProfile;
        abort_unless($profile, 404);

        $profile->update($request->validated());

        return back()->with('success', __('rml.seller.payments.bank_saved'));
    }

    /**
     * @return Builder<Commission>
     */
    private function commissionsQuery(int $userId, ?int $companyId): Builder
    {
        $query = Commission::query()
            ->with([
                'lead:id,lead_reference,status,size_m2,scheme_id',
                'lead.scheme:id,name',
            ]);

        if ($companyId) {
            $query->where(function ($q) use ($userId, $companyId) {
                $q->where('seller_user_id', $userId)
                    ->orWhere('seller_company_id', $companyId);
            });
        } else {
            $query->where('seller_user_id', $userId);
        }

        return $query;
    }

    /**
     * @return Builder<Payout>
     */
    private function payoutsQuery(int $userId, ?int $companyId): Builder
    {
        $query = Payout::query();

        if ($companyId) {
            $query->where(function ($q) use ($userId, $companyId) {
                $q->where('seller_user_id', $userId)
                    ->orWhere('seller_company_id', $companyId);
            });
        } else {
            $query->where('seller_user_id', $userId);
        }

        return $query;
    }

    /**
     * @return array<string, mixed>
     */
    private function transformCommission(Commission $c): array
    {
        $sizeM2 = $c->lead?->size_m2 !== null ? (float) $c->lead->size_m2 : null;
        $amount = $c->commission_amount !== null ? (float) $c->commission_amount : null;
        $percentage = $c->percentage !== null ? (float) $c->percentage : null;
        $baseAmount = $c->base_amount !== null ? (float) $c->base_amount : null;

        $rate = null;
        if ($percentage !== null) {
            $rate = rtrim(rtrim(number_format($percentage, 2, '.', ''), '0'), '.').'%';
        } elseif ($sizeM2 !== null && $sizeM2 > 0 && $amount !== null) {
            $rate = '€'.number_format($amount / $sizeM2, 2, '.', '').'/m²';
        } elseif ($baseAmount !== null && $baseAmount > 0 && $amount !== null && $percentage === null) {
            $rate = '€'.number_format($amount / $baseAmount, 2, '.', '').'/m²';
        }

        $metric = null;
        if ($sizeM2 !== null) {
            $metric = rtrim(rtrim(number_format($sizeM2, 2, '.', ''), '0'), '.').' m²';
        } elseif ($baseAmount !== null && $percentage === null) {
            $metric = rtrim(rtrim(number_format($baseAmount, 2, '.', ''), '0'), '.').' m²';
        }

        return [
            'id' => $c->id,
            'commission_reference' => $c->commission_reference,
            'lead_reference' => $c->lead?->lead_reference,
            'scheme' => $c->lead?->scheme?->name,
            'metric' => $metric,
            'rate' => $rate,
            'percentage' => $percentage,
            'commission_amount' => $amount,
            'status' => $c->status?->value,
            'due_at' => $c->due_at?->toIso8601String(),
            'paid_at' => $c->paid_at?->toIso8601String(),
            'statement_id' => Invoice::query()
                ->where('type', InvoiceType::CommissionStatement->value)
                ->where('user_id', $c->seller_user_id)
                ->where('total', $c->commission_amount)
                ->latest('id')
                ->value('id'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function transformPayout(Payout $p): array
    {
        $statementId = Invoice::query()
            ->where('type', InvoiceType::SellerStatement->value)
            ->where('company_id', $p->seller_company_id)
            ->where('user_id', $p->seller_user_id)
            ->get()
            ->first(fn (Invoice $invoice) => str_contains((string) $invoice->pdf_path, $p->payout_reference))
            ?->id;

        return [
            'id' => $p->id,
            'payout_reference' => $p->payout_reference,
            'lead_reference' => self::leadReferenceFromPayoutNotes($p->notes),
            'amount' => $p->amount !== null ? (float) $p->amount : null,
            'currency' => $p->currency,
            'status' => $p->status?->value,
            'due_date' => $p->due_date?->toDateString(),
            'paid_at' => $p->paid_at?->toIso8601String(),
            'notes' => $p->notes,
            'rate' => '€'.number_format(LeadPricingService::SELLER_PAYOUT_PER_M2, 2, '.', '').'/m²',
            'statement_id' => $statementId,
        ];
    }

    private function prefixedSortRequest(Request $request, string $prefix): Request
    {
        return Request::create('/', 'GET', [
            'sort' => $request->input("{$prefix}_sort"),
            'direction' => $request->input("{$prefix}_direction"),
            'per_page' => $request->input("{$prefix}_per_page"),
        ]);
    }

    private static function leadReferenceFromPayoutNotes(?string $notes): ?string
    {
        if (! $notes) {
            return null;
        }

        if (preg_match('/\b(LD-\d+)\b/', $notes, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
