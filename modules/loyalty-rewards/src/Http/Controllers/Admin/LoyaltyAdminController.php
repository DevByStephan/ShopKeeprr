<?php

namespace Modules\Loyalty\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Loyalty\Models\LoyaltyAccount;
use Modules\Loyalty\Models\LoyaltyTransaction;
use Modules\Loyalty\Services\LoyaltyService;

class LoyaltyAdminController extends Controller
{
    public function index(LoyaltyService $loyalty): View
    {
        return view('loyalty::admin.index', [
            'earnRate' => $loyalty->earnRate(),
            'redemptionPoints' => $loyalty->redemptionPoints(),
            'redemptionValue' => $loyalty->redemptionValue(),
            'accounts' => LoyaltyAccount::query()->with('user')->orderByDesc('points_balance')->paginate(15),
            'transactions' => LoyaltyTransaction::query()->with('user')->latest()->limit(15)->get(),
        ]);
    }

    public function settings(Request $request, SettingsService $settings): RedirectResponse
    {
        $data = $request->validate([
            'earn_points_per_dollar' => ['required', 'numeric', 'min:0', 'max:100'],
            'redeem_points' => ['required', 'integer', 'min:1', 'max:1000000'],
            'redeem_value' => ['required', 'numeric', 'min:0', 'max:100000'],
        ]);

        $settings->setMany([
            'loyalty.earn_points_per_dollar' => $data['earn_points_per_dollar'],
            'loyalty.redeem_points' => $data['redeem_points'],
            'loyalty.redeem_value' => $data['redeem_value'],
        ]);

        return back()->with('status', 'Loyalty settings saved.');
    }

    public function adjust(Request $request, LoyaltyService $loyalty): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'exists:users,email'],
            'points' => ['required', 'integer', 'min:-1000000', 'max:1000000', 'not_in:0'],
            'description' => ['required', 'string', 'max:200'],
        ]);

        $user = User::query()->where('email', $data['email'])->firstOrFail();
        $loyalty->adjust($user, (int) $data['points'], $data['description']);

        return back()->with('status', 'Points adjusted for '.$user->email.'.');
    }
}
