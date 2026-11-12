<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\VendorSplit;
use App\Services\Marketplace\MarketplaceException;
use App\Services\Marketplace\StripeConnect;
use App\Services\Marketplace\VendorSettlementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class PayoutController extends Controller
{
    public function index(Request $request): View
    {
        $vendor = $request->attributes->get('vendor');

        $splits = $vendor->splits()->with('order')->latest()->paginate(20);
        $balance = $vendor->splits()
            ->whereIn('status', [VendorSplit::STATUS_PENDING, VendorSplit::STATUS_HELD])
            ->get()
            ->sum(fn (VendorSplit $split) => $split->netPayout());

        $available = $vendor->splits()
            ->whereIn('status', [VendorSplit::STATUS_PENDING, VendorSplit::STATUS_HELD])
            ->whereNotNull('available_at')
            ->where('available_at', '<=', now())
            ->get()
            ->sum(fn (VendorSplit $split) => $split->netPayout());

        return view('vendor.payouts.index', compact('vendor', 'splits', 'balance', 'available'));
    }

    public function request(Request $request, VendorSettlementService $settlement): RedirectResponse
    {
        $vendor = $request->attributes->get('vendor');

        try {
            $result = $settlement->payVendor($vendor, $request->user());
        } catch (MarketplaceException $exception) {
            return back()->withErrors(['payout' => $exception->getMessage()]);
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors(['payout' => 'The payout could not be completed. We have been notified.']);
        }

        return back()->with('status', 'Payout of '.nexus_money($result['amount']).' sent. Reference: '.($result['reference'] ?? 'Stripe transfer'));
    }

    public function connectStripe(Request $request, StripeConnect $stripe): RedirectResponse
    {
        $vendor = $request->attributes->get('vendor');

        try {
            if (! $vendor->stripe_account_id) {
                $vendor->update(['stripe_account_id' => $stripe->createExpressAccount($vendor)]);
            }

            $vendor->update(['payout_method' => 'stripe']);

            return redirect()->away($stripe->accountLink(
                $vendor,
                route('vendor.payouts.stripe.return', ['refresh' => 1]),
                route('vendor.payouts.stripe.return')
            ));
        } catch (Throwable $exception) {
            return back()->withErrors(['stripe' => $exception->getMessage()]);
        }
    }

    public function stripeReturn(Request $request, StripeConnect $stripe): RedirectResponse
    {
        $vendor = $request->attributes->get('vendor');

        try {
            $stripe->refreshAccount($vendor);
        } catch (Throwable $exception) {
            return redirect()->route('vendor.payouts.index')
                ->withErrors(['stripe' => 'Stripe is still processing your details. '.$exception->getMessage()]);
        }

        return redirect()->route('vendor.payouts.index')->with('status', $vendor->stripe_payouts_enabled
            ? 'Stripe is connected. Your next payout can be sent automatically.'
            : 'Your Stripe details were saved. Finish the remaining steps to enable payouts.');
    }
}
