<x-filament-panels::page>
<div class="ly-rewards" id="liyubet-reward-wallets">
    <section class="ly-hero">
        <div><div class="ly-eyebrow">REWARDS / CUSTOMER ACCOUNTS</div><h1>Player wallets</h1><p>Explore reward balances, inspect the transaction ledger, and place suspicious accounts under review. Every financial adjustment is tracked.</p></div>
        <a class="ly-action" href="{{ \App\Filament\Pages\ReferralReports::getUrl() }}" wire:navigate>↗ Open referral reports</a>
    </section>
    @include('filament.partials.rewards-nav', ['active' => 'wallets'])

    <section class="ly-grid ly-grid-4">
        @foreach([
            ['Active wallets',number_format($walletCount),'Wallets with a recorded balance','▣',''],
            ['Total available',number_format($walletAvailable/100,2).' ETB','Unredeemed funds','◇','ly-green'],
            ['Total earned',number_format($walletEarned/100,2).' ETB','Lifetime credits','↗','ly-blue'],
            ['Frozen wallets',number_format($walletSuspended),'Blocked pending review','⊘','ly-warm']
        ] as [$label,$value,$hint,$icon,$color])
            <article class="ly-stat {{ $color }}"><small>{{ $label }}</small><span class="ly-symbol">{{ $icon }}</span><strong>{{ $value }}</strong><em>{{ $hint }}</em></article>
        @endforeach
    </section>
    <section class="ly-panel">
        <header class="ly-panel-head">
            <div><h2>Customer balances</h2><p>Review balances, statements, and account restrictions</p></div>
            <form wire:submit="search" class="ly-form-row">
                <div class="ly-field"><label for="ly-wallet-search">Search player</label><input id="ly-wallet-search" type="search" wire:model="query" class="ly-input" placeholder="Name, username or Telegram ID" style="min-width:225px" maxlength="80"></div>
                <button class="ly-action ly-primary" type="submit">Search</button>
            </form>
        </header>
        <div class="ly-table-wrap">
            <table class="ly-table">
                <thead><tr><th>Player</th><th>Telegram ID</th><th class="ly-right">Available ETB</th><th class="ly-right">Earned ETB</th><th class="ly-right">Redeemed ETB</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody>@forelse($wallets as $wallet)
                    <tr>
                        <td><div class="ly-person"><span class="ly-avatar">{{ mb_strtoupper(mb_substr($wallet->first_name ?: '?',0,1)) }}</span><span>
                            <span class="ly-name">{{ $wallet->first_name ?: 'Unnamed member' }}</span><span class="ly-sub">{{ $wallet->username ? '@'.$wallet->username : 'Telegram member' }}</span>
                        </span></div></td>
                        <td class="ly-id">{{ $wallet->tg_chat_id }}</td>
                        <td class="ly-right ly-strong">{{ number_format($wallet->balance_cents/100,2) }}</td>
                        <td class="ly-right">{{ number_format($wallet->earned_cents/100,2) }}</td>
                        <td class="ly-right">{{ number_format($wallet->redeemed_cents/100,2) }}</td>
                        <td><span class="ly-pill {{ $wallet->suspended_at ? 'ly-bad':'ly-ok' }}">{{ $wallet->suspended_at ? 'Frozen':'Active' }}</span></td>
                        <td><div class="ly-tools">
                            <button type="button" class="ly-action ly-xs" wire:click="selectUser({{ $wallet->user_id }})">View ledger</button>
                            @if($wallet->suspended_at)
                                <button type="button" class="ly-action ly-success ly-xs" wire:click="freeze({{ $wallet->user_id }}, false)" wire:confirm="Reactivate wallet?">Unfreeze</button>
                            @else
                                <button type="button" class="ly-action ly-danger ly-xs" wire:click="freeze({{ $wallet->user_id }})" wire:confirm="Suspend this wallet? New credits and redemptions will be blocked.">Freeze</button>
                            @endif
                        </div></td>
                    </tr>
                @empty<tr><td colspan="7"><div class="ly-empty"><div class="ly-empty-icon">◈</div><strong>No wallets found</strong>Wallets appear after members earn or redeem referral rewards.</div></td></tr>@endforelse</tbody>
            </table>
        </div>
        <div class="ly-pagination">{{ $wallets->links() }}</div>
    </section>

    @if($selectedUser)
        <div class="ly-split">
            <section class="ly-panel">
                <header class="ly-panel-head"><div><h2>Wallet transaction ledger</h2><p>25 most recent entries · Customer #{{ $selectedUser }}</p></div><button type="button" wire:click="clearSelection" class="ly-action ly-xs">Close</button></header>
                <div class="ly-table-wrap">
                    <table class="ly-table" style="min-width:460px">
                        <thead><tr><th>Date</th><th>Transaction</th><th class="ly-right">Change ETB</th><th class="ly-right">Balance ETB</th></tr></thead>
                        <tbody>@forelse($ledger as $entry)
                            <tr><td>{{ \Carbon\Carbon::parse($entry->created_at)->format('d M Y H:i') }}</td>
                                <td><span class="ly-pill">{{ ucwords(str_replace('_',' ',$entry->entry_type)) }}</span></td>
                                <td class="ly-right {{ $entry->change_cents >= 0 ? 'ly-strong':'' }}">{{ $entry->change_cents > 0 ? '+':'' }}{{ number_format($entry->change_cents/100,2) }}</td>
                                <td class="ly-right ly-strong">{{ number_format($entry->balance_after_cents/100,2) }}</td></tr>
                        @empty<tr><td colspan="4"><div class="ly-empty">No ledger entries for this member.</div></td></tr>@endforelse</tbody>
                    </table>
                </div>
            </section>
            <section class="ly-panel">
                <header class="ly-panel-head"><div><h2>Adjust player balance</h2><p>Owner-only financial correction</p></div><span class="ly-pill ly-review">Audited action</span></header>
                <div class="ly-panel-body">
                    <div class="ly-hint ly-warning"><span>!</span><div>Use this only for verified corrections. Credits and deductions are immediately reflected in the player's wallet. You must provide a written reason.</div></div>
                    <form wire:submit="adjust" style="margin-top:18px">
                        <div class="ly-form-grid">
                            <div class="ly-field"><label for="ly-wallet-amount">Adjustment in ETB (+/-)</label><input id="ly-wallet-amount" type="number" min="-10000" max="10000" step="1" wire:model="adjustmentEtb" class="ly-input"></div>
                            <div class="ly-field"><label for="ly-wallet-reason">Reason for change</label><input id="ly-wallet-reason" wire:model="adjustmentReason" class="ly-input" type="text" minlength="8" maxlength="250" placeholder="Enter at least 8 characters"></div>
                        </div>
                        @error('adjustmentEtb')<p class="ly-form-error" role="alert">{{ $message }}</p>@enderror
                        @error('adjustmentReason')<p class="ly-form-error" role="alert">{{ $message }}</p>@enderror
                        <div class="ly-form-footer" style="margin-top:19px">
                            <p>Changes cannot be undone automatically; corrections must be logged as a new transaction.</p>
                            <button class="ly-action ly-primary" type="submit" wire:confirm="Apply this permanent wallet adjustment?">Save adjustment</button>
                        </div>
                    </form>
                </div>
            </section>
        </div>
    @endif
</div>
</x-filament-panels::page>