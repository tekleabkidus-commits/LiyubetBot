<?php

declare(strict_types=1);

namespace App\Services\Rewards;

use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Server-authoritative referral accounting: whole-number ETB converted to cents.
 * Database uniqueness, row locks and ledger entries prevent double credit and
 * allocating the same promotion code twice.
 */
final class RewardService
{
    public function __construct(private readonly SettingsService $settings) {}

    public function enabled(): bool
    {
        $features = (array) $this->settings->get('features', []);

        return (bool) ($features['referrals'] ?? true)
            && (bool) $this->settings->get('rewards.enabled', true);
    }

    public function withdrawalsEnabled(): bool
    {
        return $this->enabled() && (bool) $this->settings->get('rewards.withdrawals_enabled', true);
    }

    /** @return list<int> */
    public function denominations(): array
    {
        $value = (array) $this->settings->get('rewards.denominations', [50, 100, 500]);
        $items = array_values(array_unique(array_filter(array_map('intval', $value), fn (int $n) => $n >= 1 && $n <= 10000)));
        sort($items);

        return $items;
    }

    /** Call only after a successful Telegram channel membership check. */
    public function qualify(User $user): bool
    {
        if (! $this->enabled() || ! $user->in_channel || $user->channel_checked_at === null
            || ! $user->referred_by_user_id || $user->referred_by_user_id === $user->id) {
            return false;
        }

        return DB::transaction(function () use ($user): bool {
            $joined = User::query()->lockForUpdate()->find($user->id);
            if (! $joined || ! $joined->in_channel || ! $joined->channel_checked_at
                || ! $joined->referred_by_user_id || $joined->referred_by_user_id === $joined->id
                || DB::table('referral_rewards')->where('referred_user_id', $joined->id)->exists()) {
                return false;
            }

            // Never credit historical Telegram signups from before launch.
            $since = (string) $this->settings->get('rewards.eligible_since', '');
            if ($since === '' || $joined->joined_at === null
                || $joined->joined_at->lt(\Carbon\Carbon::parse($since))) {
                return false;
            }

            $referrer = User::query()->find($joined->referred_by_user_id);
            if (! $referrer) {
                return false;
            }

            $amount = max(0, min(10000, (int) $this->settings->get('rewards.amount_etb', 2))) * 100;
            if ($amount === 0) {
                return false;
            }

            // A unique referred_user_id guards even when Telegram resends /start.
            $wallet = $this->lockedWallet($referrer->id);
            if ($wallet->suspended_at !== null) {
                return false;
            }

            $rewardId = DB::table('referral_rewards')->insertGetId([
                'referred_user_id' => $joined->id,
                'referrer_user_id' => $referrer->id,
                'amount_cents' => $amount,
                'qualified_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $newBalance = (int) $wallet->balance_cents + $amount;
            DB::table('referral_wallets')->where('id', $wallet->id)->update([
                'balance_cents' => $newBalance,
                'earned_cents' => (int) $wallet->earned_cents + $amount,
                'updated_at' => now(),
            ]);
            $this->ledger($referrer->id, 'referral', $amount, $newBalance, $rewardId);

            return true;
        }, 3);
    }

    private function lockedWallet(int $userId): object
    {
        DB::table('referral_wallets')->insertOrIgnore([
            'user_id' => $userId, 'balance_cents' => 0, 'earned_cents' => 0,
            'redeemed_cents' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return DB::table('referral_wallets')->where('user_id', $userId)->lockForUpdate()->first();
    }

    private function ledger(int $userId, string $type, int $change, int $balance, ?int $relatedId = null): void
    {
        DB::table('reward_ledger')->insert([
            'user_id' => $userId, 'entry_type' => $type, 'change_cents' => $change,
            'balance_after_cents' => $balance, 'related_id' => $relatedId,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function stats(User $user): array
    {
        $wallet = DB::table('referral_wallets')->where('user_id', $user->id)->first();

        return [
            'balance_cents' => (int) ($wallet->balance_cents ?? 0),
            'earned_cents' => (int) ($wallet->earned_cents ?? 0),
            'redeemed_cents' => (int) ($wallet->redeemed_cents ?? 0),
            'suspended' => $wallet?->suspended_at !== null,
            'referrals' => User::query()->where('referred_by_user_id', $user->id)->count(),
            'qualified' => DB::table('referral_rewards')->where('referrer_user_id', $user->id)->count(),
            'withdrawals' => DB::table('reward_withdrawals')->where('user_id', $user->id)->count(),
        ];
    }

    public function history(User $user, int $limit = 5): array
    {
        return DB::table('reward_withdrawals as w')
            ->join('promo_codes as p', 'p.id', '=', 'w.promo_code_id')
            ->where('w.user_id', $user->id)->orderByDesc('w.id')->limit(min(20, max(1, $limit)))
            ->get(['w.id', 'w.amount_cents', 'w.status', 'w.created_at', 'p.code_ciphertext'])
            ->map(fn ($w) => [
                'id' => $w->id, 'amount_etb' => (int) ($w->amount_cents / 100),
                'status' => $w->status, 'created_at' => $w->created_at,
                'code' => Crypt::decryptString($w->code_ciphertext),
            ])->all();
    }

    /**
     * @return array{status: string, code?: string, amount?: int}
     */
    public function redeem(User $user, int $etb, string $requestKey): array
    {
        if (! $this->withdrawalsEnabled() || ! in_array($etb, $this->denominations(), true)) {
            return ['status' => 'disabled'];
        }
        if (! preg_match('/^[A-Za-z0-9:_-]{5,120}$/', $requestKey)) {
            return ['status' => 'invalid'];
        }
        if ((bool) $this->settings->get('rewards.require_channel', true)
            && (! $user->in_channel || $user->channel_checked_at === null)) {
            return ['status' => 'not_eligible'];
        }

        return DB::transaction(function () use ($user, $etb, $requestKey): array {
            $previous = DB::table('reward_withdrawals as w')
                ->join('promo_codes as p', 'p.id', '=', 'w.promo_code_id')
                ->where('w.request_key', $requestKey)->where('w.user_id', $user->id)
                ->first(['w.amount_cents', 'p.code_ciphertext']);
            if ($previous) {
                return ['status' => 'issued', 'amount' => (int) ($previous->amount_cents / 100), 'code' => Crypt::decryptString($previous->code_ciphertext)];
            }

            $wallet = $this->lockedWallet($user->id);
            if ($wallet->suspended_at !== null) {
                return ['status' => 'suspended'];
            }
            // Avoid accidental second claims caused by rapid button tapping.
            if (DB::table('reward_withdrawals')->where('user_id', $user->id)
                ->where('created_at', '>', now()->subSeconds(5))->exists()) {
                return ['status' => 'cooldown'];
            }

            $cost = $etb * 100;
            if ((int) $wallet->balance_cents < $cost) {
                return ['status' => 'insufficient'];
            }

            // PostgreSQL row lock serializes claims even under simultaneous callbacks.
            $promo = DB::table('promo_codes')->where('status', 'available')
                ->where('denomination_cents', $cost)->orderBy('id')->lock('FOR UPDATE SKIP LOCKED')->first();

            if (! $promo) {
                return ['status' => 'no_stock'];
            }

            $withdrawalId = DB::table('reward_withdrawals')->insertGetId([
                'user_id' => $user->id, 'promo_code_id' => $promo->id,
                'request_key' => $requestKey, 'amount_cents' => $cost,
                'status' => 'issued', 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('promo_codes')->where('id', $promo->id)->update([
                'status' => 'issued', 'issued_to_user_id' => $user->id,
                'issued_at' => now(), 'updated_at' => now(),
            ]);
            $balance = (int) $wallet->balance_cents - $cost;
            DB::table('referral_wallets')->where('id', $wallet->id)->update([
                'balance_cents' => $balance,
                'redeemed_cents' => (int) $wallet->redeemed_cents + $cost,
                'updated_at' => now(),
            ]);
            $this->ledger($user->id, 'redemption', -$cost, $balance, $withdrawalId);

            return ['status' => 'issued', 'amount' => $etb, 'code' => Crypt::decryptString($promo->code_ciphertext)];
        }, 3);
    }

    /** Manual adjustments require an authenticated Owner and an audit reason. */
    public function adjust(User $user, int $changeEtb, string $reason): void
    {
        if ($changeEtb === 0 || abs($changeEtb) > 10000 || mb_strlen(trim($reason)) < 8) {
            throw new \InvalidArgumentException('Enter a valid adjustment and a reason of at least 8 characters.');
        }
        DB::transaction(function () use ($user, $changeEtb) {
            $wallet = $this->lockedWallet($user->id);
            $difference = $changeEtb * 100;
            $balance = (int) $wallet->balance_cents + $difference;
            if ($balance < 0) {
                throw new \InvalidArgumentException('Insufficient wallet balance for this deduction.');
            }
            DB::table('referral_wallets')->where('id', $wallet->id)->update([
                'balance_cents' => $balance, 'updated_at' => now(),
            ]);
            $this->ledger($user->id, 'manual_adjustment', $difference, $balance);
        }, 3);
    }

    public function suspend(int $userId, ?string $reason = null, bool $active = true): void
    {
        DB::transaction(function () use ($userId, $reason, $active) {
            $wallet = $this->lockedWallet($userId);
            DB::table('referral_wallets')->where('id', $wallet->id)->update([
                'suspended_at' => $active ? now() : null,
                'suspension_reason' => $active ? mb_substr((string) $reason, 0, 250) : null,
                'updated_at' => now(),
            ]);
        });
    }
}
