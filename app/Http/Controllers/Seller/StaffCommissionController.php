<?php

namespace App\Http\Controllers\Seller;

use App\Enums\InvoiceType;
use App\Http\Controllers\Controller;
use App\Models\Commission;
use App\Models\Invoice;
use App\Models\SellerProfile;
use App\Support\ListPagination;
use App\Support\ListSort;
use App\Support\Permissions;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StaffCommissionController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        abort_unless(
            $user?->can(Permissions::MANAGE_STAFF_COMMISSIONS)
                || $user?->can(Permissions::MANAGE_SELLER_STAFF),
            403,
        );

        $companyId = $user->sellerProfile?->company_id;
        abort_unless($companyId, 403);

        $staff = SellerProfile::query()
            ->with('user:id,name,email')
            ->where('company_id', $companyId)
            ->get()
            ->map(fn (SellerProfile $profile) => [
                'user_id' => $profile->user_id,
                'name' => $profile->user?->name,
                'email' => $profile->user?->email,
                'seller_type' => $profile->seller_type?->value,
                'commission_rate' => $profile->commission_rate !== null
                    ? (float) $profile->commission_rate
                    : null,
            ]);

        $commissionsQuery = Commission::query()
            ->with(['lead:id,lead_reference', 'sellerUser:id,name'])
            ->where('seller_company_id', $companyId);

        $sortState = ListSort::apply(
            $commissionsQuery,
            $request,
            [
                'reference' => 'commission_reference',
                'status' => 'status',
                'amount' => 'commission_amount',
                'date' => 'due_at',
            ],
            'date',
            'desc',
        );

        $perPage = ListPagination::perPage($request);

        $commissions = $commissionsQuery
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (Commission $c) => [
                'id' => $c->id,
                'commission_reference' => $c->commission_reference,
                'lead_reference' => $c->lead?->lead_reference,
                'seller_name' => $c->sellerUser?->name,
                'seller_user_id' => $c->seller_user_id,
                'percentage' => $c->percentage !== null ? (float) $c->percentage : null,
                'commission_amount' => $c->commission_amount !== null ? (float) $c->commission_amount : null,
                'status' => $c->status?->value,
                'due_at' => $c->due_at?->toIso8601String(),
                'paid_at' => $c->paid_at?->toIso8601String(),
                'statement_id' => Invoice::query()
                    ->where('type', InvoiceType::CommissionStatement->value)
                    ->where('user_id', $c->seller_user_id)
                    ->where('total', $c->commission_amount)
                    ->latest('id')
                    ->value('id'),
            ]);

        return Inertia::render('Seller/StaffCommissions', [
            'staff' => $staff,
            'commissions' => $commissions,
            'filters' => [
                'sort' => $sortState['sort'],
                'direction' => $sortState['direction'],
                'per_page' => $perPage,
            ],
        ]);
    }
}
