<?php

namespace App\Http\Controllers;

use App\Support\PortalRouter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Redirect authenticated users to their role portal dashboard
     * (or pending approval when required).
     */
    public function __invoke(Request $request): RedirectResponse
    {
        return redirect()->to(PortalRouter::homePath($request->user()));
    }
}
