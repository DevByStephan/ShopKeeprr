<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TradeController extends Controller
{
    public function index(): View
    {
        return nexus_view('trade.index', ['user' => auth()->user()]);
    }

    public function apply(Request $request): RedirectResponse
    {
        abort_if(nexus_demo(), 403, 'Trade applications are disabled in this demo.');

        $user = $request->user();

        $data = $request->validate([
            'company_name' => ['required', 'string', 'max:160'],
            'tax_id' => ['nullable', 'string', 'max:60'],
            'phone' => ['required', 'string', 'max:40'],
            'website' => ['nullable', 'url', 'max:190'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $user->update([
            'company_name' => $data['company_name'],
            'tax_id' => $data['tax_id'] ?? null,
            'phone' => $data['phone'],
            'wholesale_status' => 'pending',
            'wholesale_applied_at' => now(),
            'admin_notes' => $data['note'] ?? null,
        ]);

        return redirect()->route('storefront.trade')->with('status', 'Your trade application is in review. We will email you when it is approved.');
    }
}
