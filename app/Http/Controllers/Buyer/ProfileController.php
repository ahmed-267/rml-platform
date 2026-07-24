<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Buyer\UpdateBuyerProfileRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function show(Request $request): Response
    {
        $user = $request->user()->load(['buyerProfile.company', 'roles']);
        $company = $user->buyerProfile?->company;

        return Inertia::render('Buyer/Profile', [
            'profile' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'locale' => $user->locale,
                'approval_status' => $user->approval_status?->value,
                'role' => $user->primaryRole()?->value,
                'company' => $company ? [
                    'id' => $company->id,
                    'name' => $company->name,
                    'contact_name' => $company->contact_name,
                    'email' => $company->email,
                    'phone' => $company->phone,
                    'whatsapp' => $company->whatsapp,
                    'address' => $company->address,
                    'city' => $company->city,
                    'postcode' => $company->postcode,
                    'country' => $company->country ?? 'ES',
                    'approval_status' => $company->approval_status?->value,
                ] : null,
                'services_offered' => $user->buyerProfile?->services_offered ?? [],
                'preferred_zones' => $user->buyerProfile?->preferred_zones ?? [],
                'max_distance_km' => $user->buyerProfile?->max_distance_km !== null
                    ? (float) $user->buyerProfile->max_distance_km
                    : null,
                'billing_status' => $user->buyerProfile?->billing_status,
            ],
        ]);
    }

    public function update(UpdateBuyerProfileRequest $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validated();

        $user->fill([
            'name' => $data['name'],
            'phone' => $data['phone'] ?? $user->phone,
        ]);
        $user->save();

        $profile = $user->buyerProfile;
        if ($profile) {
            $profile->update([
                'services_offered' => $data['services_offered'] ?? $profile->services_offered,
                'preferred_zones' => $data['preferred_zones'] ?? $profile->preferred_zones,
                'max_distance_km' => $data['max_distance_km'] ?? $profile->max_distance_km,
            ]);

            $company = $profile->company;
            if ($company) {
                $company->update([
                    'name' => $data['company_name'] ?? $company->name,
                    'contact_name' => $data['contact_name'] ?? $company->contact_name,
                    'email' => $data['company_email'] ?? $company->email,
                    'phone' => $data['company_phone'] ?? $company->phone,
                    'whatsapp' => $data['whatsapp'] ?? $company->whatsapp,
                    'address' => $data['address'] ?? $company->address,
                    'city' => $data['city'] ?? $company->city,
                    'postcode' => $data['postcode'] ?? $company->postcode,
                    'country' => $data['country'] ?? $company->country ?? 'ES',
                ]);
            }
        }

        return back()->with('success', __('rml.buyer.profile.saved'));
    }
}
