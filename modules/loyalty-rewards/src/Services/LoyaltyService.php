<?php

namespace Modules\Loyalty\Services;

use App\Enums\DiscountType;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Store;
use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Loyalty\Models\LoyaltyAccount;
use Modules\Loyalty\Models\LoyaltyTransaction;

class LoyaltyService
{
    public function __construct(private readonly SettingsService $settings)
    {
    }

    public function account(User $user): LoyaltyAccount
    {
        return LoyaltyAccount::query()->firstOrCreate(
            ['user_id' => $user->id],
            ['points_balance' => 0, 'lifetime_points' => 0]
        );
    }

    public function earnRate(): float
    {
        return max(0, (float) $this->settings->get('loyalty.earn_points_per_dollar', 1));
    }

    public function redemptionPoints(): int
    {
        return max(1, (int) $this->settings->get('loyalty.redeem_points', 100));
    }

    public function redemptionValue(): float
    {
        return max(0, (float) $this->settings->get('loyalty.redeem_value', 5));
    }

    public function awardForOrder(Order $order): void
    {
        if (! $order->user_id || $order->payment_status?->value !== 'paid') {
            return;
        }

        if (LoyaltyTransaction::query()->where('order_id', $order->id)->where('type', 'earned')->exists()) {
            return;
        }

        $points = (int) floor((float) $order->grand_total * $this->earnRate());

        if ($points <= 0) {
            return;
        }

        DB::transaction(function () use ($order, $points) {
            $account = $this->account($order->user);
            $account->increment('points_balance', $points);
            $account->increment('lifetime_points', $points);

            LoyaltyTransaction::query()->create([
                'user_id' => $order->user_id,
                'points' => $points,
                'type' => 'earned',
                'description' => 'Earned on order '.$order->number,
                'order_id' => $order->id,
            ]);
        });
    }

    public function adjust(User $user, int $points, string $description): void
    {
        DB::transaction(function () use ($user, $points, $description) {
            $account = $this->account($user);

            if ($points < 0 && $account->points_balance + $points < 0) {
                throw ValidationException::withMessages(['points' => 'That adjustment would make the balance negative.']);
            }

            $account->increment('points_balance', $points);

            if ($points > 0) {
                $account->increment('lifetime_points', $points);
            }

            LoyaltyTransaction::query()->create([
                'user_id' => $user->id,
                'points' => $points,
                'type' => 'adjustment',
                'description' => $description,
            ]);
        });
    }

    public function redeem(User $user): Coupon
    {
        $points = $this->redemptionPoints();
        $value = $this->redemptionValue();

        return DB::transaction(function () use ($user, $points, $value) {
            $account = $this->account($user);

            if ($account->points_balance < $points) {
                throw ValidationException::withMessages([
                    'loyalty' => 'You need '.$points.' points to redeem this reward. Your current balance is '.$account->points_balance.'.',
                ]);
            }

            $coupon = Coupon::query()->create([
                'store_id' => Store::default()?->id,
                'code' => 'LOYAL-'.Str::upper(Str::random(8)),
                'type' => DiscountType::Fixed,
                'value' => $value,
                'usage_limit' => 1,
                'usage_limit_per_customer' => 1,
                'used_count' => 0,
                'starts_at' => now(),
                'expires_at' => now()->addDays(90),
                'is_active' => true,
            ]);

            $account->decrement('points_balance', $points);

            LoyaltyTransaction::query()->create([
                'user_id' => $user->id,
                'points' => -$points,
                'type' => 'redeemed',
                'description' => 'Reward coupon '.$coupon->code,
                'coupon_id' => $coupon->id,
            ]);

            return $coupon;
        });
    }
}
