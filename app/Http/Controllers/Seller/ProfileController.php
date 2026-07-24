<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Http\Requests\Seller\UpdateSellerProfileRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function show(Request $request): Response
    {
        $user = $request->user()->load(['sellerProfile.company', 'roles']);

        return Inertia::render('Seller/Profile', [
            'profile' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'locale' => $user->locale,
                'approval_status' => $user->approval_status?->value,
                'role' => $user->primaryRole()?->value,
                'seller_type' => $user->sellerProfile?->seller_type?->value,
                'company' => $user->sellerProfile?->company ? [
                    'id' => $user->sellerProfile->company->id,
                    'name' => $user->sellerProfile->company->name,
                ] : null,
                'commission_rate' => $user->sellerProfile?->commission_rate !== null
                    ? (float) $user->sellerProfile->commission_rate
                    : null,
                'bank_account_iban' => $user->sellerProfile?->bank_account_iban,
                'bank_account_name' => $user->sellerProfile?->bank_account_name,
                'payout_method' => $user->sellerProfile?->payout_method,
            ],
        ]);
    }

    public function update(UpdateSellerProfileRequest $request): RedirectResponse
    {
        $user = $request->user();
        $user->fill($request->validated());

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        return back()->with('success', __('rml.seller.profile.saved'));
    }
}
