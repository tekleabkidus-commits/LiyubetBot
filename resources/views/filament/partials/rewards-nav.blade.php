<nav class="ly-nav" aria-label="Reward management sections">
    @foreach([
      ['reports','Overview',\App\Filament\Pages\ReferralReports::getUrl()],
      ['inventory','Promo inventory',\App\Filament\Pages\PromoInventory::getUrl()],
      ['withdrawals','Withdrawals',\App\Filament\Pages\RewardWithdrawals::getUrl()],
      ['wallets','Player wallets',\App\Filament\Pages\RewardWallets::getUrl()],
      ['settings','Configuration',\App\Filament\Pages\RewardSettings::getUrl()]
    ] as [$id, $label, $url])
        <a href="{{ $url }}" wire:navigate class="{{ ($active ?? '') === $id ? 'is-active' : '' }}" @if(($active ?? '') === $id) aria-current="page" @endif><i class="ly-nav-dot" aria-hidden="true"></i>{{ $label }}</a>
    @endforeach
</nav>