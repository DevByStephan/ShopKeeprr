<?php

namespace App\Http\Controllers\Admin;

use App\Enums\StoreMode;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorSplit;
use App\Services\Marketplace\MarketplaceException;
use App\Services\Marketplace\StripeConnect;
use App\Services\Marketplace\VendorSettlementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class VendorController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));
        $status = (string) $request->query('status');

        $vendors = Vendor::query()
            ->withCount('products')
            ->when($search !== '', fn ($query) => $query->where(fn ($q) => $q
                ->where('name', 'like', '%'.$search.'%')
                ->orWhere('email', 'like', '%'.$search.'%')))
            ->when(in_array($status, ['pending', 'approved', 'suspended'], true), fn ($query) => $query->where('status', $status))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $vendors->getCollection()->transform(function (Vendor $vendor): Vendor {
            $vendor->setAttribute('balance', $vendor->splits()
                ->whereIn('status', [VendorSplit::STATUS_PENDING, VendorSplit::STATUS_HELD])
                ->get()
                ->sum(fn (VendorSplit $split) => $split->netPayout()));

            return $vendor;
        });

        return view('admin.vendors.index', [
            'vendors' => $vendors,
            'search' => $search,
            'status' => $status,
            'counts' => [
                'all' => Vendor::query()->count(),
                'pending' => Vendor::query()->where('status', Vendor::STATUS_PENDING)->count(),
                'approved' => Vendor::query()->where('status', Vendor::STATUS_APPROVED)->count(),
                'suspended' => Vendor::query()->where('status', Vendor::STATUS_SUSPENDED)->count(),
            ],
        ]);
    }

    public function create(): View
    {
        $this->ensureMarketplace();

        return view('admin.vendors.form', [
            'vendor' => new Vendor([
                'status' => Vendor::STATUS_PENDING,
                'payout_method' => 'manual',
                'country' => nexus_setting('store.country', 'US'),
            ]),
            'users' => $this->availableUsers(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureMarketplace();

        $data = $this->validated($request);
        $data['slug'] = $this->uniqueSlug($data['name']);
        $data['store_id'] = auth()->user()?->store_id;

        if (! empty($data['user_id'])) {
            $data['email'] = User::query()->findOrFail($data['user_id'])->email;
        }

        $vendor = Vendor::query()->create($data);

        return redirect()->route('admin.vendors.show', $vendor)
            ->with('status', $vendor->name.' was created. Connect their payout account when you are ready.');
    }

    public function show(Vendor $vendor): View
    {
        $vendor->load(['user', 'products.images']);

        $splits = $vendor->splits()
            ->with(['order'])
            ->latest()
            ->paginate(25);

        $totals = [
            'lifetime' => (float) $vendor->splits()->where('status', VendorSplit::STATUS_PAID)->sum('payout_total'),
            'balance' => (float) $vendor->splits()
                ->whereIn('status', [VendorSplit::STATUS_PENDING, VendorSplit::STATUS_HELD])
                ->get()
                ->sum(fn (VendorSplit $split) => $split->netPayout()),
            'refunded' => (float) $vendor->splits()->sum('refunded_total'),
            'orders' => (int) $vendor->splits()->count(),
        ];

        return view('admin.vendors.show', compact('vendor', 'splits', 'totals'));
    }

    public function edit(Vendor $vendor): View
    {
        return view('admin.vendors.form', [
            'vendor' => $vendor,
            'users' => $this->availableUsers($vendor),
        ]);
    }

    public function update(Request $request, Vendor $vendor): RedirectResponse
    {
        $this->ensureMarketplace();

        $data = $this->validated($request, $vendor);

        if ($data['name'] !== $vendor->name) {
            $data['slug'] = $this->uniqueSlug($data['name'], $vendor);
        }

        $vendor->update($data);

        return redirect()->route('admin.vendors.show', $vendor)->with('status', 'Seller settings saved.');
    }

    public function status(Request $request, Vendor $vendor): RedirectResponse
    {
        $this->ensureMarketplace();

        $data = $request->validate([
            'status' => ['required', Rule::in([Vendor::STATUS_PENDING, Vendor::STATUS_APPROVED, Vendor::STATUS_SUSPENDED])],
        ]);

        $vendor->update([
            'status' => $data['status'],
            'approved_at' => $data['status'] === Vendor::STATUS_APPROVED ? ($vendor->approved_at ?? now()) : $vendor->approved_at,
        ]);

        return back()->with('status', $vendor->name.' is now '.$data['status'].'.');
    }

    public function connectStripe(Vendor $vendor, StripeConnect $stripe): RedirectResponse
    {
        try {
            if (! $vendor->stripe_account_id) {
                $vendor->update(['stripe_account_id' => $stripe->createExpressAccount($vendor)]);
            }

            $url = $stripe->accountLink(
                $vendor,
                route('admin.vendors.stripe.return', $vendor),
                route('admin.vendors.stripe.return', $vendor)
            );

            return redirect()->away($url);
        } catch (Throwable $exception) {
            return back()->withErrors(['stripe' => $exception->getMessage()]);
        }
    }

    /**
     * Stripe redirects the seller's browser back here with a normal GET, so
     * the onboarding return and refresh URLs must not be POST-only.
     */
    public function stripeReturn(Vendor $vendor, StripeConnect $stripe): RedirectResponse
    {
        try {
            $stripe->refreshAccount($vendor);

            return redirect()->route('admin.vendors.show', $vendor)->with('status', $vendor->stripe_payouts_enabled
                ? 'Stripe is ready to send this seller transfers.'
                : 'Stripe saved the onboarding progress. The seller still has steps to finish.');
        } catch (Throwable $exception) {
            return redirect()->route('admin.vendors.show', $vendor)
                ->withErrors(['stripe' => $exception->getMessage()]);
        }
    }

    public function refreshStripe(Vendor $vendor, StripeConnect $stripe): RedirectResponse
    {
        try {
            $stripe->refreshAccount($vendor);
            $vendor->update(['payout_method' => 'stripe']);

            return back()->with('status', $vendor->stripe_payouts_enabled
                ? 'Stripe is ready to send this seller transfers.'
                : 'Stripe saved the onboarding progress. The seller still has steps to finish.');
        } catch (Throwable $exception) {
            return back()->withErrors(['stripe' => $exception->getMessage()]);
        }
    }

    public function payout(Request $request, Vendor $vendor, VendorSettlementService $settlement): RedirectResponse
    {
        try {
            $result = $settlement->payVendor($vendor, $request->user(), $request->boolean('force'));

            return back()->with('status', 'Paid '.$result['paid'].' split(s) totalling '.nexus_money($result['amount']).'.');
        } catch (MarketplaceException $exception) {
            return back()->withErrors(['payout' => $exception->getMessage()]);
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors(['payout' => 'The payout could not be completed. '.$exception->getMessage()]);
        }
    }

    private function ensureMarketplace(): void
    {
        abort_unless(nexus_mode() === StoreMode::MultiVendor, 404);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Vendor $vendor = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40'],
            'country' => ['nullable', 'string', 'size:2'],
            'description' => ['nullable', 'string', 'max:2000'],
            'user_id' => [
                'nullable', 'integer', 'exists:users,id',
                Rule::unique('vendors', 'user_id')->ignore($vendor?->id),
            ],
            'status' => ['required', Rule::in([Vendor::STATUS_PENDING, Vendor::STATUS_APPROVED, Vendor::STATUS_SUSPENDED])],
            'commission_type' => ['nullable', Rule::in(['percentage', 'fixed', 'combined'])],
            'commission_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'commission_fixed' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'payout_method' => ['required', Rule::in(['stripe', 'paypal', 'manual'])],
            'paypal_email' => ['nullable', 'email', 'max:190'],
            'payout_hold_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'admin_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        if (($data['payout_method'] ?? null) === 'paypal' && empty($data['paypal_email'])) {
            $data['paypal_email'] = $data['email'];
        }

        return $data;
    }

    private function uniqueSlug(string $name, ?Vendor $ignore = null): string
    {
        $base = Str::slug($name) ?: 'vendor';
        $slug = $base;
        $suffix = 2;

        while (Vendor::query()->where('slug', $slug)->when($ignore, fn ($q) => $q->whereKeyNot($ignore->id))->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    /**
     * @return \Illuminate\Support\Collection<int, User>
     */
    private function availableUsers(?Vendor $vendor = null)
    {
        return User::query()
            ->where('role', 'customer')
            ->whereNotIn('id', Vendor::query()->whereNotNull('user_id')->when($vendor, fn ($q) => $q->whereKeyNot($vendor->id))->pluck('user_id'))
            ->orderBy('name')
            ->get();
    }
}
