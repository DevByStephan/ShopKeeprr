<?php

namespace App\Services;

use App\Enums\StoreMode;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductModeState;
use App\Models\StoreModeSwitch;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorSplit;
use App\Services\Payments\PaymentManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class StoreModeService
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly CartService $carts,
        private readonly PaymentManager $payments,
    ) {
    }

    /**
     * A read-only preview of everything a switch would touch.
     *
     * @return array<string, mixed>
     */
    public function report(StoreMode $target): array
    {
        $current = $this->settings->mode();
        $catalog = $this->catalogReport($target);
        $orders = $this->orderReport();
        $subscriptions = $this->subscriptionReport();
        $vendors = $this->vendorReport();
        $carts = $this->cartReport();
        $integrity = $this->integrityReport();

        $blockers = [];
        $warnings = [];

        if ($current === $target) {
            $warnings[] = [
                'code' => 'same_mode',
                'message' => 'The store is already running in '.$target->label().' mode.',
            ];
        }

        if ($subscriptions['active'] > 0 && $target !== StoreMode::Subscription) {
            $warnings[] = [
                'code' => 'existing_subscriptions',
                'message' => $subscriptions['active'].' active subscription(s) continue to renew in their original transaction context. They are not converted to one-time purchases.',
            ];
        }

        if ($subscriptions['active'] > 0
            && $target !== StoreMode::Subscription
            && $subscriptions['auto_renew']
            && ! $subscriptions['gateway_ready']) {
            $blockers[] = [
                'code' => 'subscription_gateway_missing',
                'message' => 'Active subscriptions renew automatically, but no subscription-capable gateway is enabled. Enable Stripe or PayPal before leaving Subscription mode, or turn off automatic renewal first.',
            ];
        }

        if ($vendors['outstanding'] > 0 && $target !== StoreMode::MultiVendor) {
            if ($vendors['unpayoutable'] > 0) {
                $names = collect($vendors['unpayoutable_list'])->pluck('vendor')->filter()->take(5)->implode(', ');
                $blockers[] = [
                    'code' => 'vendor_payout_unavailable',
                    'message' => $vendors['unpayoutable'].' seller(s) have outstanding balances without a usable payout destination'
                        .($names !== '' ? ' ('.$names.')' : '')
                        .'. Connect their payout method or settle the balance before leaving Multi-Vendor mode.',
                ];
            } else {
                $warnings[] = [
                    'code' => 'vendor_balances',
                    'message' => nexus_money($vendors['outstanding']).' in seller balances remains payable. Payouts continue from the original Multi-Vendor order context.',
                ];
            }
        }

        if ($catalog['incompatible'] > 0) {
            $warnings[] = [
                'code' => 'catalog_archive',
                'message' => $catalog['will_archive'].' product(s) will be archived for '.$target->label().'. Merchant-hidden products are left untouched and automatic archives can be restored later.',
            ];
        }

        if ($catalog['total'] === 0) {
            $warnings[] = [
                'code' => 'empty_catalog',
                'message' => 'The catalog is empty. The switch is allowed, but the storefront will have nothing to sell until products are added.',
            ];
        }

        if ($catalog['compatible'] === 0 && $catalog['total'] > 0) {
            $warnings[] = [
                'code' => 'no_compatible_products',
                'message' => 'No existing product is sellable in '.$target->label().'. The catalog will be archived and a new catalog will be required.',
            ];
        }

        if ($integrity['conflicts'] > 0) {
            $warnings[] = [
                'code' => 'product_conflicts',
                'message' => $integrity['conflicts'].' product(s) have contradictory attributes (seller + wholesale + subscription). Resolve them in Catalog → Integrity before selling.',
            ];
        }

        if ($integrity['cross_model_order_items'] > 0) {
            $warnings[] = [
                'code' => 'cross_model_orders',
                'message' => $integrity['cross_model_order_items'].' historical order line(s) do not match their order model. They are preserved unchanged; review the integrity report for reporting-only anomalies.',
            ];
        }

        if ($integrity['cross_model_vendor_splits'] > 0) {
            $warnings[] = [
                'code' => 'cross_model_splits',
                'message' => $integrity['cross_model_vendor_splits'].' historical seller split(s) are attached to non-Multi-Vendor orders. Payout obligations are preserved unchanged.',
            ];
        }

        if ($carts['stale'] > 0) {
            $warnings[] = [
                'code' => 'stale_carts',
                'message' => $carts['stale'].' cart(s) were created before the current model revision. They will be repriced and revalidated after the switch.',
            ];
        }

        if ($catalog['conflict_products'] > 0) {
            $warnings[] = [
                'code' => 'catalog_conflicts',
                'message' => $catalog['conflict_products'].' product(s) cannot be sold in any model until their conflicting attributes are fixed.',
            ];
        }

        return [
            'current' => $current?->value,
            'target' => $target->value,
            'target_label' => $target->label(),
            'target_description' => $target->description(),
            'revision' => $this->settings->modeRevision(),
            'catalog' => $catalog,
            'orders' => $orders,
            'subscriptions' => $subscriptions,
            'vendors' => $vendors,
            'carts' => $carts,
            'integrity' => $integrity,
            'blockers' => $blockers,
            'warnings' => $warnings,
            'can_switch' => $blockers === [],
        ];
    }

    /**
     * Perform the switch. The database transition is atomic; no payment or
     * other irreversible external operation runs inside it.
     */
    public function switchTo(
        StoreMode $target,
        User $admin,
        bool $confirmed = false,
        bool $live = false,
        ?string $note = null,
        ?string $ip = null,
        ?string $userAgent = null,
    ): StoreModeSwitch {
        if (! $confirmed) {
            throw ValidationException::withMessages([
                'mode' => 'Review the preflight report and confirm the model switch.',
            ]);
        }

        $this->settings->flushCache();
        $report = $this->report($target);

        if ($report['blockers'] !== []) {
            throw new StoreModeSwitchException($report, $report['blockers'][0]['message']);
        }

        $current = $this->settings->mode();

        if ($current === $target) {
            throw ValidationException::withMessages([
                'mode' => 'The store is already running in '.$target->label().'.',
            ]);
        }

        try {
            $switch = DB::transaction(function () use ($target, $admin, $live, $note, $ip, $userAgent) {
                // Serialize switches and freeze the two settings that define
                // the active model/revision.
                DB::table('settings')
                    ->whereIn('key', ['store.mode', 'store.mode_revision'])
                    ->lockForUpdate()
                    ->get();

                $this->settings->flushCache();
                $report = $this->report($target);

                if ($report['blockers'] !== []) {
                    throw new StoreModeSwitchException($report, $report['blockers'][0]['message']);
                }

                $currentRaw = DB::table('settings')->where('key', 'store.mode')->value('value');
                $current = $currentRaw ? StoreMode::tryFrom((string) $currentRaw) : null;

                if ($current === $target) {
                    throw ValidationException::withMessages([
                        'mode' => 'The store is already running in '.$target->label().'.',
                    ]);
                }

                $revision = max(0, (int) DB::table('settings')->where('key', 'store.mode_revision')->value('value')) + 1;

                $catalog = $this->applyCatalogState($target);
                $carts = $this->carts->reconcileAll($target, $revision);

                $this->settings->setMode($target);
                $this->settings->setModeRevision($revision);
                $this->settings->set('store.live', $live, 'store');
                $this->settings->set('store.setup_completed', true, 'store');

                $affected = [
                    'revision' => $revision,
                    'catalog' => $catalog,
                    'carts' => $carts,
                    'preflight' => [
                        'blockers' => $report['blockers'],
                        'warnings' => $report['warnings'],
                    ],
                ];

                $switch = StoreModeSwitch::query()->create([
                    'from_mode' => $current?->value,
                    'to_mode' => $target->value,
                    'mode_revision' => $revision,
                    'user_id' => $admin->id,
                    'status' => 'completed',
                    'note' => $note,
                    'affected' => $affected,
                ]);

                AuditLog::query()->create([
                    'user_id' => $admin->id,
                    'action' => 'store.mode_switched',
                    'model_type' => StoreModeSwitch::class,
                    'model_id' => $switch->id,
                    'old_values' => ['mode' => $current?->value, 'revision' => $revision - 1],
                    'new_values' => ['mode' => $target->value, 'revision' => $revision],
                    'ip_address' => $ip,
                    'user_agent' => $userAgent,
                    'url' => '/admin/settings?section=mode',
                    'method' => 'POST',
                    'created_at' => now(),
                ]);

                return $switch;
            });
        } catch (Throwable $exception) {
            $this->settings->flushCache();

            throw $exception;
        }

        $this->settings->flushCache();

        return $switch;
    }

    /**
     * Re-apply the current model to one product after an admin edit.
     */
    public function syncProductState(Product $product, ?StoreMode $mode = null): void
    {
        $mode ??= $this->settings->mode();

        if (! $mode) {
            return;
        }

        $state = ProductModeState::query()
            ->where('product_id', $product->id)
            ->where('mode', $mode->value)
            ->first();

        if (! $product->supportsMode($mode)) {
            if (! $state || $state->origin === ProductModeState::ORIGIN_SYSTEM) {
                ProductModeState::query()->updateOrCreate(
                    ['product_id' => $product->id, 'mode' => $mode->value],
                    [
                        'is_visible' => false,
                        'origin' => ProductModeState::ORIGIN_SYSTEM,
                        'reason' => $product->modeUnavailableReason($mode) ?? 'Not available in '.$mode->label().'.',
                        'archived_at' => $state?->archived_at ?? now(),
                        'restored_at' => null,
                    ],
                );
            }

            return;
        }

        if ($state && ! $state->is_visible && $state->origin === ProductModeState::ORIGIN_SYSTEM) {
            $state->update([
                'is_visible' => true,
                'reason' => null,
                'restored_at' => now(),
            ]);
        }
    }

    /**
     * Merchant-chosen visibility is independent from automatic archiving.
     */
    public function setManualVisibility(Product $product, StoreMode $mode, bool $hidden): void
    {
        if (! $hidden && ! $product->supportsMode($mode)) {
            $this->syncProductState($product, $mode);

            return;
        }

        ProductModeState::query()->updateOrCreate(
            ['product_id' => $product->id, 'mode' => $mode->value],
            [
                'is_visible' => ! $hidden,
                'origin' => ProductModeState::ORIGIN_MANUAL,
                'reason' => $hidden ? 'Hidden by the merchant in '.$mode->label().' mode.' : null,
                'archived_at' => $hidden ? now() : null,
                'restored_at' => $hidden ? null : now(),
            ],
        );
    }

    /**
     * @return array{archived: int, restored: int, kept: int}
     */
    private function applyCatalogState(StoreMode $mode): array
    {
        $counts = ['archived' => 0, 'restored' => 0, 'kept' => 0];

        Product::query()
            ->withCount('activeSubscriptionPlans')
            ->orderBy('id')
            ->chunkById(200, function ($products) use ($mode, &$counts): void {
                $states = ProductModeState::query()
                    ->where('mode', $mode->value)
                    ->whereIn('product_id', $products->pluck('id'))
                    ->get()
                    ->keyBy('product_id');

                foreach ($products as $product) {
                    $state = $states->get($product->id);

                    if (! $product->supportsMode($mode)) {
                        if ($state && ! $state->is_visible && $state->origin === ProductModeState::ORIGIN_MANUAL) {
                            $counts['kept']++;
                            continue;
                        }

                        if ($state && ! $state->is_visible && $state->origin === ProductModeState::ORIGIN_SYSTEM) {
                            $counts['kept']++;
                            continue;
                        }

                        ProductModeState::query()->updateOrCreate(
                            ['product_id' => $product->id, 'mode' => $mode->value],
                            [
                                'is_visible' => false,
                                'origin' => ProductModeState::ORIGIN_SYSTEM,
                                'reason' => $product->modeUnavailableReason($mode) ?? 'Not available in '.$mode->label().'.',
                                'archived_at' => now(),
                                'restored_at' => null,
                            ],
                        );
                        $counts['archived']++;
                        continue;
                    }

                    if ($state && ! $state->is_visible && $state->origin === ProductModeState::ORIGIN_SYSTEM) {
                        $state->update([
                            'is_visible' => true,
                            'reason' => null,
                            'restored_at' => now(),
                        ]);
                        $counts['restored']++;
                        continue;
                    }

                    $counts['kept']++;
                }
            });

        return $counts;
    }

    /**
     * @return array<string, mixed>
     */
    private function catalogReport(StoreMode $target): array
    {
        $products = Product::query()
            ->withCount('activeSubscriptionPlans')
            ->with(['modeStates' => fn ($query) => $query->where('mode', $target->value)])
            ->orderBy('name')
            ->get();

        $total = $products->count();
        $compatible = 0;
        $incompatible = 0;
        $willArchive = 0;
        $alreadyArchived = 0;
        $willRestore = 0;
        $conflictProducts = 0;
        $sample = [];

        foreach ($products as $product) {
            $state = $product->modeStates->first();

            if ($product->modeConflicts() !== []) {
                $conflictProducts++;
            }

            if (! $product->supportsMode($target)) {
                $incompatible++;
                $already = $state && ! $state->is_visible;

                if ($already) {
                    $alreadyArchived++;
                } else {
                    $willArchive++;
                }

                if (count($sample) < 12) {
                    $sample[] = [
                        'name' => $product->name,
                        'reason' => $product->modeUnavailableReason($target) ?? 'Not available in this model.',
                        'already_archived' => $already,
                    ];
                }

                continue;
            }

            $compatible++;

            if ($state && ! $state->is_visible && $state->origin === ProductModeState::ORIGIN_SYSTEM) {
                $willRestore++;
            }
        }

        return [
            'total' => $total,
            'compatible' => $compatible,
            'incompatible' => $incompatible,
            'will_archive' => $willArchive,
            'already_archived' => $alreadyArchived,
            'will_restore' => $willRestore,
            'conflict_products' => $conflictProducts,
            'sample' => $sample,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function orderReport(): array
    {
        $byMode = [];
        $pending = 0;
        $unpaidPending = 0;

        $rows = Order::query()
            ->select('mode', 'status', 'payment_status', DB::raw('count(*) as aggregate'))
            ->groupBy('mode', 'status', 'payment_status')
            ->get();

        foreach ($rows as $row) {
            $mode = $row->mode?->value ?? 'unset';
            $count = (int) $row->aggregate;
            $byMode[$mode] = ($byMode[$mode] ?? 0) + $count;

            if (in_array($row->status?->value, ['pending', 'processing', 'on_hold'], true)) {
                $pending += $count;

                if (in_array($row->payment_status?->value, ['pending', 'authorized'], true)) {
                    $unpaidPending += $count;
                }
            }
        }

        return [
            'total' => array_sum($byMode),
            'by_mode' => $byMode,
            'pending' => $pending,
            'unpaid_pending' => $unpaidPending,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function subscriptionReport(): array
    {
        $active = Subscription::query()
            ->whereIn('status', ['trialing', 'active', 'past_due'])
            ->count();

        $next = Subscription::query()
            ->whereIn('status', ['trialing', 'active', 'past_due'])
            ->whereNotNull('next_billing_at')
            ->min('next_billing_at');

        return [
            'active' => $active,
            'next_billing_at' => $next,
            'auto_renew' => (bool) nexus_setting('subscriptions.auto_renew', true),
            'gateway_ready' => $this->subscriptionGatewayReady(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function vendorReport(): array
    {
        $splits = VendorSplit::query()
            ->whereIn('status', [VendorSplit::STATUS_PENDING, VendorSplit::STATUS_HELD])
            ->where('payout_total', '>', 0)
            ->with('vendor')
            ->get();

        $outstanding = 0.0;
        $byVendor = [];
        $unpayoutableList = [];

        foreach ($splits as $split) {
            $net = $split->netPayout();

            if ($net <= 0) {
                continue;
            }

            $outstanding += $net;
            $vendor = $split->vendor;
            $key = $vendor?->id ?? 0;

            if (! isset($byVendor[$key])) {
                $byVendor[$key] = [
                    'vendor' => $vendor?->name ?? 'Deleted seller',
                    'amount' => 0.0,
                    'splits' => 0,
                    'payout_method' => $vendor?->payout_method ?? 'missing',
                ];
            }

            $byVendor[$key]['amount'] += $net;
            $byVendor[$key]['splits']++;

            if (! $this->vendorCanBePaid($vendor)) {
                $unpayoutableList[] = [
                    'vendor' => $vendor?->name ?? 'Deleted seller',
                    'amount' => round($net, 2),
                    'method' => $vendor?->payout_method ?? 'missing',
                ];
            }
        }

        return [
            'outstanding' => round($outstanding, 2),
            'payable_count' => count($byVendor),
            'unpayoutable' => count($unpayoutableList),
            'unpayoutable_list' => $unpayoutableList,
            'by_vendor' => array_values($byVendor),
            'approved_vendors' => Vendor::query()->where('status', Vendor::STATUS_APPROVED)->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function cartReport(): array
    {
        $revision = $this->settings->modeRevision();
        $current = $this->settings->mode()?->value;

        $stale = \App\Models\Cart::query()
            ->whereHas('items')
            ->where(function ($query) use ($revision, $current): void {
                $query->whereNull('mode_revision')
                    ->orWhere('mode_revision', '!=', $revision)
                    ->orWhereNull('mode')
                    ->orWhere('mode', '!=', $current);
            })
            ->count();

        return [
            'with_items' => \App\Models\Cart::query()->whereHas('items')->count(),
            'items' => (int) \App\Models\CartItem::query()->sum('quantity'),
            'stale' => $stale,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function integrityReport(): array
    {
        $conflictProducts = Product::query()
            ->withCount('activeSubscriptionPlans')
            ->get()
            ->filter(fn (Product $product) => $product->modeConflicts() !== []);

        $crossModelOrderItems = OrderItem::query()
            ->where(function ($query): void {
                $query->where(function ($vendorQuery): void {
                    $vendorQuery->whereNotNull('vendor_id')
                        ->whereHas('order', fn ($order) => $order->where('mode', '!=', StoreMode::MultiVendor->value));
                })->orWhere(function ($subscriptionQuery): void {
                    $subscriptionQuery->where('is_subscription', true)
                        ->whereHas('order', fn ($order) => $order->where('mode', '!=', StoreMode::Subscription->value));
                })->orWhere(function ($oneTimeQuery): void {
                    $oneTimeQuery->where('is_subscription', false)
                        ->whereHas('order', fn ($order) => $order->where('mode', StoreMode::Subscription->value));
                });
            })
            ->count();

        $crossModelVendorSplits = VendorSplit::query()
            ->whereHas('order', fn ($order) => $order->where('mode', '!=', StoreMode::MultiVendor->value))
            ->count();

        return [
            'conflicts' => $conflictProducts->count(),
            'conflict_products' => $conflictProducts->take(20)->map(fn (Product $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'reasons' => $product->modeConflicts(),
            ])->values()->all(),
            'cross_model_order_items' => $crossModelOrderItems,
            'cross_model_vendor_splits' => $crossModelVendorSplits,
        ];
    }

    private function subscriptionGatewayReady(): bool
    {
        $available = array_keys($this->payments->available(true));

        return array_diff($available, ['manual']) !== [];
    }

    private function vendorCanBePaid(?Vendor $vendor): bool
    {
        if (! $vendor) {
            return false;
        }

        return match ($vendor->payout_method) {
            'stripe' => $vendor->canReceiveStripeTransfers(),
            'paypal' => filled($vendor->paypal_email),
            default => true,
        };
    }
}
