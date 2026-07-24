<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\BuyerRegistrationRequest;
use App\Http\Requests\Auth\SellerRegistrationRequest;
use App\Services\RegistrationService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    public function __construct(
        private readonly RegistrationService $registrationService,
    ) {}

    /**
     * Registration choice page (Sell / Buy / Homeowner link).
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    public function createSeller(): Response
    {
        return Inertia::render('Auth/RegisterSeller');
    }

    public function createBuyer(): Response
    {
        return Inertia::render('Auth/RegisterBuyer');
    }

    public function storeSeller(SellerRegistrationRequest $request): RedirectResponse
    {
        $user = $this->registrationService->registerSeller($request->registrationPayload());

        event(new Registered($user));

        Auth::login($user);

        $request->session()->regenerate();

        return redirect()->route('pending-approval');
    }

    public function storeBuyer(BuyerRegistrationRequest $request): RedirectResponse
    {
        $user = $this->registrationService->registerBuyer($request->registrationPayload());

        event(new Registered($user));

        Auth::login($user);

        $request->session()->regenerate();

        return redirect()->route('pending-approval');
    }
}
