<?php

namespace App\Services;

use App\Enums\StoreMode;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SubscriptionPlan;

class StoreModeCompatibility
{
    /**
     * Why this cart line cannot be sold in the supplied model, or null.
     */
    public function itemProblem(CartItem $item, ?StoreMode $mode): ?string
    {
        if (! $mode) {
            return 'The store model is not configured.';
        }

        $product = $item->product;

        if (! $product) {
            return 'This product is no longer available.';
        }

        if (! $product->is_active) {
            return $product->name.' is not currently published.';
        }

        if ($problem = $this->productProblem($product, $mode)) {
            return $problem;
        }

        if ($product->vendor_id && ! $product->vendor?->isApproved()) {
            return $product->name.' is sold by a seller who is not approved.';
        }

        if ($item->variant_id !== null) {
            $variant = $item->variant;

            if (! $variant || ! $variant->is_active || (int) $variant->product_id !== (int) $product->id) {
                return 'The selected option for '.$product->name.' is no longer available.';
            }
        }

        if ($mode === StoreMode::Subscription) {
            $plan = $item->plan;

            if ($plan) {
                if (! $plan->is_active || (int) $plan->product_id !== (int) $product->id) {
                    return $product->name.' needs an active subscription plan in Subscription mode.';
                }
            } elseif ($product->hasActiveSubscriptionPlans()) {
                return $product->name.' must be purchased with one of its subscription plans, not as a one-time item.';
            }
        } elseif ($item->subscription_plan_id !== null) {
            return 'Subscription plans are only sold in Subscription mode.';
        }

        return null;
    }

    public function productProblem(Product $product, StoreMode $mode): ?string
    {
        if (! $product->supportsMode($mode)) {
            return $product->name.': '.($product->modeUnavailableReason($mode) ?? 'This product is not available in '.$mode->label().'.');
        }

        return null;
    }

    public function assertProduct(Product $product, StoreMode $mode, ?ProductVariant $variant = null, ?SubscriptionPlan $plan = null): void
    {
        $message = $this->productProblem($product, $mode);

        if ($message) {
            throw \Illuminate\Validation\ValidationException::withMessages(['cart' => $message]);
        }

        if (! $product->is_active) {
            throw \Illuminate\Validation\ValidationException::withMessages(['cart' => $product->name.' is not currently published.']);
        }

        if ($variant && ((int) $variant->product_id !== (int) $product->id || ! $variant->is_active)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['cart' => 'That product option is not available.']);
        }

        if ($mode === StoreMode::Subscription) {
            if ($plan) {
                if (! $plan->is_active || (int) $plan->product_id !== (int) $product->id) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['cart' => 'Choose an active subscription plan for '.$product->name.'.']);
                }
            } elseif ($product->hasActiveSubscriptionPlans()) {
                throw \Illuminate\Validation\ValidationException::withMessages(['cart' => $product->name.' must be purchased with a subscription plan.']);
            }
        } elseif ($plan) {
            throw \Illuminate\Validation\ValidationException::withMessages(['cart' => 'Subscription plans are only sold in Subscription mode.']);
        }
    }
}
