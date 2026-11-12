<?php

namespace App\Services\Marketplace;

use App\Enums\StoreMode;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Vendor;
use App\Models\VendorSplit;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Turns an order into one ledger row per vendor.
 *
 * Shipping and tax stay with the platform. Vendors earn their item subtotal
 * after their share of the order discount, minus commission. Discounts are
 * allocated with integer cents and a largest-remainder pass, so the per-item
 * amounts always add back up to the order discount exactly.
 */
class VendorSplitService
{
    public function __construct(
        private readonly CommissionCalculator $calculator,
    ) {
    }

    /**
     * @return Collection<int, VendorSplit>
     */
    public function createForOrder(Order $order): Collection
    {
        $order->loadMissing('items.product.vendor');

        if ($order->mode !== StoreMode::MultiVendor) {
            return collect();
        }

        $splits = DB::transaction(function () use ($order): Collection {
            $items = $order->items
                ->filter(fn (OrderItem $item) => $item->product?->vendor_id !== null)
                ->values();

            if ($items->isEmpty()) {
                return collect();
            }

            // Allocate the order discount across every line, including
            // store-owned products. Otherwise a mixed cart would dump the
            // whole discount on the sellers.
            $allItems = $order->items->values();
            $allSubtotals = $allItems->map(fn (OrderItem $item) => $this->calculator->cents($item->line_subtotal))->all();
            $allDiscounts = $this->allocate(max(0, $this->calculator->cents($order->discount_total)), $allSubtotals);
            $discountByItem = [];

            foreach ($allItems as $index => $item) {
                $lineDiscount = min($allSubtotals[$index], $allDiscounts[$index]);
                $discountByItem[$item->id] = $lineDiscount;
                $lineDiscountMoney = $this->calculator->money($lineDiscount);

                if (round((float) $item->discount_amount, 2) !== $lineDiscountMoney) {
                    $item->update(['discount_amount' => $lineDiscountMoney]);
                }
            }

            /** @var array<int, array{vendor: Vendor, subtotal: int, discount: int, items: array<int, array{item: OrderItem, subtotal: int, discount: int, basis: int}>}> $groups */
            $groups = [];

            foreach ($items as $item) {
                $vendor = $item->product->vendor;
                $lineSubtotal = $this->calculator->cents($item->line_subtotal);
                $lineDiscount = $discountByItem[$item->id] ?? 0;
                $basis = max(0, $lineSubtotal - $lineDiscount);

                $groups[$vendor->id] ??= [
                    'vendor' => $vendor,
                    'subtotal' => 0,
                    'discount' => 0,
                    'items' => [],
                ];

                $groups[$vendor->id]['subtotal'] += $lineSubtotal;
                $groups[$vendor->id]['discount'] += $lineDiscount;
                $groups[$vendor->id]['items'][] = [
                    'item' => $item,
                    'subtotal' => $lineSubtotal,
                    'discount' => $lineDiscount,
                    'basis' => $basis,
                ];
            }

            $created = collect();

            foreach ($groups as $group) {
                // A split is a financial snapshot. Re-running this method
                // must never rewrite a held, paid or reversed ledger row.
                $existing = VendorSplit::query()
                    ->where('order_id', $order->id)
                    ->where('vendor_id', $group['vendor']->id)
                    ->first();

                if ($existing) {
                    $created->push($existing);

                    continue;
                }

                $basis = max(0, $group['subtotal'] - $group['discount']);
                $rule = $group['vendor']->commissionRule();
                $commission = $this->calculator->calculate($basis, $rule);
                $itemBases = array_column($group['items'], 'basis');
                $itemCommissions = $this->allocate($commission, $itemBases);

                foreach ($group['items'] as $position => $line) {
                    $lineCommission = $itemCommissions[$position];

                    $line['item']->update([
                        'vendor_id' => $group['vendor']->id,
                        'discount_amount' => $this->calculator->money($line['discount']),
                        'commission_amount' => $this->calculator->money($lineCommission),
                        'vendor_total' => $this->calculator->money(max(0, $line['basis'] - $lineCommission)),
                    ]);
                }

                $split = new VendorSplit([
                    'order_id' => $order->id,
                    'vendor_id' => $group['vendor']->id,
                ]);

                $split->fill([
                    'status' => VendorSplit::STATUS_PENDING,
                    'currency' => $order->currency,
                    'subtotal' => $this->calculator->money($group['subtotal']),
                    'discount_total' => $this->calculator->money($group['discount']),
                    'commission_total' => $this->calculator->money($commission),
                    'payout_total' => $this->calculator->money(max(0, $basis - $commission)),
                    'payout_method' => $group['vendor']->payout_method,
                    'failure_reason' => null,
                    'available_at' => null,
                    'paid_at' => null,
                ]);
                $split->save();
                $created->push($split);
            }

            return $created;
        });

        return $splits;
    }

    /**
     * Put each paid order's vendor money into the hold window.
     */
    public function releaseOnPayment(Order $order): void
    {
        if ($order->mode !== StoreMode::MultiVendor) {
            return;
        }

        VendorSplit::query()
            ->where('order_id', $order->id)
            ->where('status', VendorSplit::STATUS_PENDING)
            ->with('vendor')
            ->get()
            ->each(function (VendorSplit $split): void {
                $days = $split->vendor?->payoutHoldDays() ?? 0;

                $split->update([
                    'status' => VendorSplit::STATUS_HELD,
                    'payout_method' => $split->vendor?->payout_method ?? $split->payout_method,
                    'available_at' => now()->addDays($days),
                ]);
            });
    }

    /**
     * Record a refund against the vendor ledger.
     *
     * The refund is shared between the item pool and the platform-owned
     * shipping/tax share in proportion to the order. Each vendor's share is
     * capped at what they still have to lose, because the platform always
     * returns its own commission as part of the refund.
     *
     * @return array<int, float> split id => amount to claw back from the vendor
     */
    public function recordRefund(Order $order, float $amount): array
    {
        if ($order->mode !== StoreMode::MultiVendor || $amount <= 0) {
            return [];
        }

        return DB::transaction(function () use ($order, $amount): array {
            $splits = VendorSplit::query()
                ->where('order_id', $order->id)
                ->where('status', '!=', VendorSplit::STATUS_REVERSED)
                ->get();

            if ($splits->isEmpty()) {
                return [];
            }

            $refundCents = $this->calculator->cents($amount);
            $grandTotal = max(1, $this->calculator->cents($order->grand_total));
            $basis = $splits->sum(fn (VendorSplit $split) => $this->calculator->cents($split->subtotal - $split->discount_total));
            $vendorShare = (int) round($refundCents * min(1, $basis / $grandTotal));
            $allocations = $this->allocate(
                min($vendorShare, $basis),
                $splits->map(fn (VendorSplit $split) => $this->calculator->cents($split->subtotal - $split->discount_total))->all()
            );

            $clawbacks = [];

            foreach ($splits as $index => $split) {
                $stillOwed = $this->calculator->cents($split->netPayout());
                $clawback = min($allocations[$index], $stillOwed);

                if ($clawback <= 0) {
                    continue;
                }

                $split->update([
                    'refunded_total' => $this->calculator->money($this->calculator->cents($split->refunded_total) + $clawback),
                    'status' => $split->fresh()->netPayout() <= 0.009
                        ? VendorSplit::STATUS_REVERSED
                        : $split->status,
                ]);

                $clawbacks[$split->id] = $this->calculator->money($clawback);
            }

            return $clawbacks;
        });
    }

    /**
     * Split an integer amount proportionally, then distribute the rounding
     * remainder to the lines with the biggest fractional part. The result
     * always sums to the requested amount (never above the weights).
     *
     * @param  array<int, int>  $weights
     * @return array<int, int>
     */
    public function allocate(int $amount, array $weights): array
    {
        $count = count($weights);
        $result = array_fill(0, $count, 0);

        if ($count === 0 || $amount <= 0) {
            return $result;
        }

        $scale = max(1, array_sum($weights));
        $amount = min($amount, max(0, array_sum($weights)));
        $fractions = [];

        foreach ($weights as $index => $weight) {
            $numerator = $amount * max(0, $weight);
            $result[$index] = intdiv($numerator, $scale);
            $fractions[$index] = $numerator % $scale;
        }

        $remaining = $amount - array_sum($result);

        arsort($fractions);

        foreach (array_keys($fractions) as $index) {
            if ($remaining <= 0) {
                break;
            }

            if ($result[$index] < $weights[$index]) {
                $result[$index]++;
                $remaining--;
            }
        }

        // The weight cap can leave a cent behind when one line is already at
        // its limit. Give it to any line that still has room.
        foreach (array_keys($weights) as $index) {
            if ($remaining <= 0) {
                break;
            }

            $room = $weights[$index] - $result[$index];

            if ($room > 0) {
                $give = min($room, $remaining);
                $result[$index] += $give;
                $remaining -= $give;
            }
        }

        return $result;
    }
}
