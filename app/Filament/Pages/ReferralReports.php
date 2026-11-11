<?php

namespace App\Filament\Pages;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Filament\Pages\Page;

class ReferralReports extends Page
{
    protected string $view = 'filament.pages.referral-reports';
    protected static ?string $navigationLabel = 'Referral Reports';
    protected static string|\UnitEnum|null $navigationGroup = 'Rewards';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';
    protected static ?int $navigationSort = 1;
    public int $days = 30;

    public static function canAccess(): bool
    {
        return auth()->user()?->isOwner() === true;
    }

    public function selectPeriod(int $days): void
    {
        abort_unless(in_array($days, [1, 7, 30, 90], true), 422);
        $this->days = $days;
    }

    protected function getViewData(): array
    {
        $cutoff = now()->subDays($this->days);
        $leaders = DB::table('referral_rewards as r')
            ->join('users as u', 'u.id', '=', 'r.referrer_user_id')
            ->where('r.qualified_at', '>=', $cutoff)
            ->groupBy('r.referrer_user_id', 'u.first_name', 'u.tg_chat_id')
            ->select('r.referrer_user_id', 'u.first_name', 'u.tg_chat_id')
            ->selectRaw('count(*) as qualified, sum(r.amount_cents) as earned_cents')
            ->orderByDesc('qualified')->limit(100)->get();
        $daily = DB::table('referral_rewards')->where('qualified_at', '>=', $cutoff)
            ->selectRaw('date(qualified_at) as day, count(*) as referrals, sum(amount_cents) as amount_cents')
            ->groupByRaw('date(qualified_at)')->orderByDesc('day')->limit(90)->get();
        return [
            'totalUsers' => User::count(),
            'referredUsers' => User::whereNotNull('referred_by_user_id')->count(),
            'qualified' => DB::table('referral_rewards')->count(),
            'earned' => (int) DB::table('referral_rewards')->sum('amount_cents'),
            'walletBalance' => (int) DB::table('referral_wallets')->sum('balance_cents'),
            'issued' => DB::table('reward_withdrawals')->count(),
            'redeemed' => (int) DB::table('reward_withdrawals')->sum('amount_cents'),
            'availableCodes' => DB::table('promo_codes')->where('status', 'available')->count(),
            'leaders' => $leaders,
            'daily' => $daily,
        ];
    }

    public function export(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        abort_unless(static::canAccess(), 403);
        $cutoff = now()->subDays($this->days);
        return response()->streamDownload(function () use ($cutoff) {
            $stream = fopen('php://output', 'w');
            fputcsv($stream, ['Day', 'Qualified referrals', 'Earned ETB']);
            DB::table('referral_rewards')->where('qualified_at', '>=', $cutoff)
                ->selectRaw('date(qualified_at) as day, count(*) as referrals, sum(amount_cents) as amount_cents')
                ->groupByRaw('date(qualified_at)')->orderBy('day')->cursor()
                ->each(fn ($r) => fputcsv($stream, [$r->day, $r->referrals, number_format($r->amount_cents / 100, 2, '.', '')]));
            fclose($stream);
        }, 'liyubet-referrals.csv');
    }
}
