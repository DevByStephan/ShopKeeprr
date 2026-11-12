<?php

namespace Modules\Loyalty\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Modules\Loyalty\Models\LoyaltyTransaction;
use Modules\Loyalty\Services\LoyaltyService;

class LoyaltyController extends Controller
{
    public function index(LoyaltyService $loyalty): View
    {
        $user = auth()->user();
        $account = $loyalty->account($user);

        return view('loyalty::index', [
            'account' => $account,
            'transactions' => LoyaltyTransaction::query()->where('user_id', $user->id)->latest()->paginate(15),
            'earnRate' => $loyalty->earnRate(),
            'redemptionPoints' => $loyalty->redemptionPoints(),
            'redemptionValue' => $loyalty->redemptionValue(),
        ]);
    }

    public function redeem(LoyaltyService $loyalty): RedirectResponse
    {
        $coupon = $loyalty->redeem(auth()->user());

        return back()->with('status', 'Reward unlocked. Use coupon '.$coupon->code.' at checkout.');
    }
}
