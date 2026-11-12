<?php

namespace App\Http\Controllers\Admin;

use App\Enums\WholesaleStatus;
use App\Http\Controllers\Controller;
use App\Models\CustomerTier;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        $query = User::query()->where('role', '!=', 'admin')->with(['tier'])->latest();

        if ($request->filled('q')) {
            $term = '%'.$request->string('q')->toString().'%';
            $query->where(fn ($sub) => $sub->where('name', 'like', $term)
                ->orWhere('email', 'like', $term)
                ->orWhere('company_name', 'like', $term));
        }

        if ($request->filled('wholesale_status')) {
            $query->where('wholesale_status', $request->string('wholesale_status')->toString());
        }

        $customers = $query->paginate(20)->withQueryString();

        return view('admin.customers.index', compact('customers'));
    }

    public function show(User $customer): View
    {
        abort_if($customer->role === 'admin', 404);

        $customer->load(['tier', 'orders' => fn ($query) => $query->latest()->take(8), 'subscriptions.product', 'subscriptions.plan']);

        return view('admin.customers.show', [
            'customer' => $customer,
            'tiers' => CustomerTier::query()->orderBy('priority')->get(),
        ]);
    }

    public function update(Request $request, User $customer): RedirectResponse
    {
        abort_if($customer->role === 'admin', 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($customer->id)],
            'company_name' => ['nullable', 'string', 'max:160'],
            'tax_id' => ['nullable', 'string', 'max:60'],
            'phone' => ['nullable', 'string', 'max:40'],
            'tier_id' => ['nullable', 'integer', 'exists:customer_tiers,id'],
            'wholesale_status' => ['required', Rule::in(['none', 'pending', 'approved', 'rejected'])],
            'admin_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($data['wholesale_status'] === WholesaleStatus::Approved->value) {
            $data['wholesale_approved_at'] = $customer->wholesale_approved_at ?? now();
            if (empty($data['tier_id'])) {
                $data['tier_id'] = CustomerTier::query()->where('slug', 'wholesale')->value('id');
            }
        }

        if ($data['wholesale_status'] === WholesaleStatus::Pending->value && ! $customer->wholesale_applied_at) {
            $data['wholesale_applied_at'] = now();
        }

        $customer->update($data);

        return back()->with('status', 'Customer updated.');
    }
}
