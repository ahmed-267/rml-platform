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
            ->through(fn (Commission $c) => [
                'id' => $c->id,
                'commission_reference' => $c->commission_reference,
                'lead_reference' => $c->lead?->lead_reference,
                'percentage' => $c->percentage !== null ? (float) $c->percentage : null,
                'commission_amount' => $c->commission_amount !== null ? (float) $c->commission_amount : null,
                'status' => $c->status?->value,
                'due_at' => $c->due_at?->toIso8601String(),
                'paid_at' => $c->paid_at?->toIso8601String(),
                'seller_name' => $c->relationLoaded('sellerUser') ? $c->sellerUser?->name : null,
                'statement_id' => Invoice::query()
                    ->where('type', InvoiceType::CommissionStatement->value)
                    ->where('user_id', $c->seller_user_id)
                    ->where('total', $c->commission_amount)
                    ->latest('id')
                    ->value('id'),
            ]);

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
            ->through(function (Payout $p) {
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
                    'statement_id' => $statementId,
                ];
            });

        return Inertia::render('Seller/Payments', [
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
            ->with(['lead:id,lead_reference,status']);

        if ($companyId) {
            $query->with(['sellerUser:id,name'])
                ->where(function ($q) use ($userId, $companyId) {
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
