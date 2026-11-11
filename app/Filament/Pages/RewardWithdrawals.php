<?php

namespace App\Filament\Pages;

use App\Services\AuditLogger;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;

class RewardWithdrawals extends Page
{
    protected string $view = 'filament.pages.reward-withdrawals';
    protected static ?string $navigationLabel = 'Withdrawal History';
    protected static string|\UnitEnum|null $navigationGroup = 'Rewards';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-receipt-percent';
    protected static ?int $navigationSort = 3;
    public string $filter = 'all';
    public ?int $revealedWithdrawalId = null;
    public ?string $revealedCode = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->isOwner() === true;
    }

    public function setFilter(string $status): void
    {
        abort_unless(in_array($status, ['all', 'issued', 'flagged'], true), 422);
        $this->filter = $status;
    }

    public function reveal(int $id): void
    {
        abort_unless(static::canAccess(), 403);
        $record = DB::table('reward_withdrawals as w')
            ->join('promo_codes as p', 'p.id', '=', 'w.promo_code_id')
            ->where('w.id', $id)->first(['w.id','p.code_ciphertext']);
        abort_unless($record !== null, 404);
        $this->revealedWithdrawalId = $id;
        $this->revealedCode = Crypt::decryptString($record->code_ciphertext);
        app(AuditLogger::class)->log('rewards.withdrawal.code_revealed', null, ['withdrawal_id' => $id]);
    }

    public function hideCode(): void
    {
        $this->revealedWithdrawalId = null;
        $this->revealedCode = null;
    }

    public function flag(int $id, bool $flag = true): void
    {
        abort_unless(static::canAccess(), 403);
        $from = $flag ? 'issued' : 'flagged';
        $to = $flag ? 'flagged' : 'issued';
        $updated = DB::table('reward_withdrawals')->where('id', $id)->where('status', $from)
            ->update(['status' => $to, 'updated_at' => now()]);
        if ($updated) {
            app(AuditLogger::class)->log('rewards.withdrawal.review', null, ['withdrawal_id' => $id, 'status' => $to]);
            Notification::make()->title('Withdrawal review updated')->success()->send();
        }
    }

    public function export(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        abort_unless(static::canAccess(), 403);
        return response()->streamDownload(function () {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Withdrawal ID', 'Telegram ID', 'Amount ETB', 'Status', 'Date', 'Promo ID']);
            DB::table('reward_withdrawals as w')->join('users as u', 'u.id', '=', 'w.user_id')
                ->orderBy('w.id')->select('w.id','u.tg_chat_id','w.amount_cents','w.status','w.created_at','w.promo_code_id')
                ->cursor()->each(fn ($w) => fputcsv($output, [$w->id, $w->tg_chat_id, $w->amount_cents / 100, $w->status, $w->created_at, $w->promo_code_id]));
            fclose($output);
        }, 'liyubet-withdrawal-history.csv');
    }

    protected function getViewData(): array
    {
        $withdrawals = DB::table('reward_withdrawals as w')
            ->join('users as u', 'u.id', '=', 'w.user_id')
            ->when($this->filter !== 'all', fn ($q) => $q->where('w.status', $this->filter))
            ->select('w.id', 'u.tg_chat_id', 'u.first_name', 'w.amount_cents', 'w.status', 'w.created_at', 'w.promo_code_id')
            ->orderByDesc('w.id')->limit(100)->get();
        return [
            'withdrawals' => $withdrawals,
            'totalIssued' => DB::table('reward_withdrawals')->count(),
            'totalEtb' => DB::table('reward_withdrawals')->sum('amount_cents') / 100,
            'flagged' => DB::table('reward_withdrawals')->where('status', 'flagged')->count(),
        ];
    }
}
