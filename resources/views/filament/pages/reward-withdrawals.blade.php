<x-filament-panels::page>
<div class="ly-rewards" id="liyubet-reward-withdrawals">
    <section class="ly-hero">
        <div><div class="ly-eyebrow">REWARDS / SETTLEMENTS</div><h1>Withdrawal history</h1><p>Track each promo-code redemption, review suspicious withdrawals, and securely recover issued codes when customers need help.</p></div>
        <button type="button" class="ly-action" wire:click="export" wire:loading.attr="disabled" wire:target="export">↓ Export all withdrawals</button>
    </section>
    @include('filament.partials.rewards-nav', ['active' => 'withdrawals'])

    <section class="ly-grid ly-grid-3">
        @foreach([
            ['Promo withdrawals',number_format($totalIssued),'Lifetime redemptions','⇥',''],
            ['Redeemed value',number_format($totalEtb,2).' ETB','Total value of issued codes','◇','ly-green'],
            ['Needs review',number_format($flagged),'Manually flagged withdrawals','!','ly-warm']
        ] as [$label,$value,$hint,$icon,$color])
            <article class="ly-stat {{ $color }}"><small>{{ $label }}</small><span class="ly-symbol">{{ $icon }}</span><strong>{{ $value }}</strong><em>{{ $hint }}</em></article>
        @endforeach
    </section>

    @if($revealedCode)
        <div class="ly-panel" role="status" aria-live="polite">
            <div class="ly-panel-head"><div><h2>Private code · Withdrawal #{{ $revealedWithdrawalId }}</h2><p>Visible only to authorized Owners. Access is recorded in the audit log.</p></div>
                <button type="button" class="ly-action ly-ghost" wire:click="hideCode">✕ Hide code</button>
            </div>
            <div class="ly-panel-body">
                <p class="ly-small">Verify the customer's account before sharing this code. Never paste it into public chats.</p>
                <div class="ly-code-box">{{ $revealedCode }}</div>
            </div>
        </div>
    @endif

    <div class="ly-hint ly-warning"><span aria-hidden="true">!</span><div><strong>Settlement protection:</strong> Once a promo code has been issued, flagging a withdrawal does not refund the player or return the code to stock. Contact the customer before making any manual wallet adjustment.</div></div>

    <section class="ly-panel">
        <header class="ly-panel-head">
            <div><h2>Redemption ledger</h2><p>View your complete, auditable history of player redemptions</p></div>
            <div class="ly-tools">
                <form wire:submit="searchWithdrawals" class="ly-inline-flex">
                    <input class="ly-input" style="width:190px;min-height:36px" type="search" wire:model="searchTerm" placeholder="Telegram ID or reference" aria-label="Search withdrawal records">
                    <button type="submit" class="ly-action ly-xs">Search</button>
                </form>
                <div class="ly-segment" aria-label="Review status">
                    @foreach(['all'=>'All','issued'=>'Issued','flagged'=>'Under review'] as $key=>$name)
                        <button type="button" wire:click="setFilter('{{ $key }}')" class="{{ $filter===$key ? 'is-active' : '' }}" aria-pressed="{{ $filter===$key ? 'true':'false' }}">{{ $name }}</button>
                    @endforeach
                </div>
            </div>
        </header>
        <div class="ly-table-wrap">
            <table class="ly-table">
                <thead><tr><th>Reference</th><th>Player</th><th>Amount</th><th>Issued on</th><th>Redemption status</th><th>Review actions</th></tr></thead>
                <tbody>@forelse($withdrawals as $w)
                    <tr>
                        <td><span class="ly-name">#{{ $w->id }}</span><span class="ly-sub">Promo #{{ $w->promo_code_id }}</span></td>
                        <td><div class="ly-person"><span class="ly-avatar">{{ mb_strtoupper(mb_substr($w->first_name ?: '?',0,1)) }}</span>
                            <span><span class="ly-name">{{ $w->first_name ?: 'Unnamed player' }}</span><span class="ly-sub">{{ $w->tg_chat_id }}</span></span></div></td>
                        <td class="ly-strong">{{ number_format($w->amount_cents/100,2) }} ETB</td>
                        <td>{{ \Carbon\Carbon::parse($w->created_at)->format('d M Y') }}<span class="ly-sub">{{ \Carbon\Carbon::parse($w->created_at)->format('H:i') }}</span></td>
                        <td><span class="ly-pill {{ $w->status === 'issued' ? 'ly-ok':'ly-review' }}">{{ $w->status === 'flagged' ? 'Needs review':'Issued' }}</span></td>
                        <td><div class="ly-tools">
                            <button type="button" class="ly-action ly-xs" wire:click="reveal({{ $w->id }})">◉ View code</button>
                            @if($w->status === 'issued')
                                <button type="button" class="ly-action ly-xs ly-danger" wire:click="flag({{ $w->id }})" wire:confirm="Flag this redemption for review? This will not refund or reactivate its promo code.">Flag</button>
                            @else
                                <button type="button" class="ly-action ly-xs ly-success" wire:click="flag({{ $w->id }}, false)">Clear flag</button>
                            @endif
                        </div></td>
                    </tr>
                @empty<tr><td colspan="6"><div class="ly-empty"><div class="ly-empty-icon">▣</div><strong>No withdrawals match</strong>Issued promo-code redemptions will appear here automatically.</div></td></tr>@endforelse</tbody>
            </table>
        </div>
        <div class="ly-pagination">{{ $withdrawals->links() }}</div>
    </section>
</div>
</x-filament-panels::page>