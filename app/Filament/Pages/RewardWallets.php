<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Rewards\RewardService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Livewire\WithPagination;

class RewardWallets extends Page
{
    use WithPagination;
    protected string $view = 'filament.pages.reward-wallets';
    protected static ?string $navigationLabel = 'Player Wallets';
    protected static string|\UnitEnum|null $navigationGroup = 'Rewards';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-wallet';
    protected static ?int $navigationSort = 4;

    public string $query = '';
    public ?int $selectedUser = null;
    public int $adjustmentEtb = 0;
    public string $adjustmentReason = '';

    public static function canAccess(): bool
    {
        return auth()->user()?->isOwner() === true;
    }

    public function search(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->query = mb_substr(trim($this->query), 0, 80);
        $this->resetPage();
    }

    public function clearSelection(): void
    {
        $this->selectedUser = null;
        $this->adjustmentEtb = 0;
        $this->adjustmentReason = '';
    }

    public function selectUser(int $userId): void
    {
        abort_unless(static::canAccess(), 403);
        $user = User::findOrFail($userId);
        $this->selectedUser = $user->id;
        $this->adjustmentEtb = 0;
        $this->adjustmentReason = '';
    }

    public function freeze(int $userId, bool $freeze = true): void
    {
        abort_unless(static::canAccess(), 403);
        User::findOrFail($userId);
        app(RewardService::class)->suspend($userId, 'Owner review', $freeze);
        app(AuditLogger::class)->log('rewards.wallet.suspension', null, ['user_id' => $userId, 'suspended' => $freeze]);
        Notification::make()->title($freeze ? 'Wallet suspended' : 'Wallet reactivated')->success()->send();
    }

    public function adjust(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->validate([
            'selectedUser' => ['required', 'integer', 'exists:users,id'],
            'adjustmentEtb' => ['required', 'integer', 'between:-10000,10000', 'not_in:0'],
            'adjustmentReason' => ['required', 'string', 'min:8', 'max:250'],
        ]);
        $user = User::findOrFail($this->selectedUser);
        try {
            app(RewardService::class)->adjust($user, $this->adjustmentEtb, $this->adjustmentReason);
        } catch (InvalidArgumentException $e) {
            $this->addError('adjustmentEtb', $e->getMessage());
            return;
        }
        app(AuditLogger::class)->log('rewards.wallet.adjusted', null, [
            'user_id' => $user->id, 'change_etb' => $this->adjustmentEtb,
            'reason' => $this->adjustmentReason,
        ]);
        $this->adjustmentEtb = 0;
        $this->adjustmentReason = '';
        Notification::make()->title('Wallet adjustment saved in ledger')->success()->send();
    }

    protected function getViewData(): array
    {
        $wallets = DB::table('referral_wallets as w')->join('users as u', 'u.id', '=', 'w.user_id')
            ->when($this->query !== '', function ($q) {
                $search = $this->query;
                return $q->where(function ($q) use ($search) {
                    $q->where('u.first_name', 'ilike', '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%')
                        ->orWhere('u.username', 'ilike', '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%')
                        ->when(ctype_digit($search), fn ($q) => $q->orWhere('u.tg_chat_id', (int) $search));
                });
            })
            ->select('w.user_id','w.balance_cents','w.earned_cents','w.redeemed_cents','w.suspended_at','u.tg_chat_id','u.first_name','u.username')
            ->orderByDesc('w.id')->paginate(25);
        $ledger = $this->selectedUser ? DB::table('reward_ledger')->where('user_id', $this->selectedUser)->orderByDesc('id')->limit(25)->get() : collect();
        return [
            'wallets' => $wallets,
            'ledger' => $ledger,
            'walletCount' => DB::table('referral_wallets')->count(),
            'walletAvailable' => (int) DB::table('referral_wallets')->sum('balance_cents'),
            'walletEarned' => (int) DB::table('referral_wallets')->sum('earned_cents'),
            'walletSuspended' => DB::table('referral_wallets')->whereNotNull('suspended_at')->count(),
        ];
    }
}
