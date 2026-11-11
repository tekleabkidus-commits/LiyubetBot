<x-filament-panels::page>
<div class="ly-rewards" id="liyubet-reward-reports">
    <section class="ly-hero">
        <div>
            <div class="ly-eyebrow">LIYUBET / REWARDS STUDIO</div>
            <h1>Referral intelligence</h1>
            <p>One place to understand referrals, reward liabilities, and promo-code redemptions. Numbers are calculated from your real transactions.</p>
        </div>
        <button type="button" class="ly-action" wire:click="export" wire:loading.attr="disabled" wire:target="export"><span aria-hidden="true">↓</span> Export report</button>
    </section>
    @include('filament.partials.rewards-nav', ['active' => 'reports'])

    <section class="ly-grid ly-grid-4" aria-label="Referral performance">
        @foreach([
            ['Total Telegram members',number_format($totalUsers),'All-time audience','◎',''],
            ['Qualified referrals',number_format($qualified),'All-time verified referrals','↗','ly-green'],
            ['Total rewards earned',number_format($earned / 100,2).' ETB','All-time referral credits','◈',''],
            ['Unspent wallet liability',number_format($walletBalance / 100,2).' ETB','Total outstanding balance','≋','ly-warm'],
        ] as [$label,$value,$hint,$icon,$color])
        <article class="ly-stat {{ $color }}"><small>{{ $label }}</small><span class="ly-symbol" aria-hidden="true">{{ $icon }}</span><strong>{{ $value }}</strong><em>{{ $hint }}</em></article>
        @endforeach
    </section>

    <div class="ly-panel">
        <div class="ly-panel-head">
            <div><h2>Growth &amp; rewards</h2><p>Recent performance over your selected reporting period</p></div>
            <div class="ly-inline-flex">
                <div class="ly-segment" aria-label="Reporting period">
                    @foreach([1,7,30,90] as $period)
                        <button type="button" wire:click="selectPeriod({{ $period }})" wire:loading.attr="disabled" class="{{ $days === $period ? 'is-active' : '' }}" aria-pressed="{{ $days === $period ? 'true' : 'false' }}">{{ $period }}D</button>
                    @endforeach
                </div>
            </div>
        </div>
        <div class="ly-panel-body">
            <div class="ly-grid ly-grid-3">
                @foreach([
                    ['Qualified in period',number_format($periodQualified),'New verified referrals','✓'],
                    ['Rewards credited in period',number_format($periodEarned / 100,2).' ETB','Amounts earned during period','⇧'],
                    ['Total redemptions',number_format($issued),'Promo codes allocated all time','▣']
                ] as [$label,$value,$hint,$icon])
                    <div class="ly-stat"><small>{{ $label }}</small><span class="ly-symbol">{{ $icon }}</span><strong>{{ $value }}</strong><em>{{ $hint }}</em></div>
                @endforeach
            </div>
            <div class="ly-grid ly-grid-2" style="margin-top:20px">
                <div class="ly-panel" style="box-shadow:none">
                    <div class="ly-panel-head"><div><h2>Referral trend</h2><p>Qualified referrals for the most recent {{ count($chartDays) }} days</p></div></div>
                    <div class="ly-panel-body">
                        <div class="ly-chart" role="img" aria-label="Daily qualified referral counts">
                            @foreach($chartDays as $point)
                                <div class="ly-chart-col" title="{{ $point['date'] }}: {{ $point['count'] }} qualified referrals">
                                    <div class="ly-chart-bar" style="height:{{ max(3, 138 * $point['count'] / max(1,$chartMax)) }}px">
                                        @if($point['count'] > 0)<span>{{ $point['count'] }}</span>@endif
                                    </div>
                                    <small>{{ $point['label'] }}</small>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
                <div class="ly-panel" style="box-shadow:none">
                    <div class="ly-panel-head"><div><h2>Reward activity</h2><p>Lifetime redemptions and stock availability</p></div></div>
                    <div class="ly-panel-body">
                        <div class="ly-stock-grid">
                            <div class="ly-stock-card"><small>Codes available</small><strong class="ly-stock-number">{{ number_format($availableCodes) }}</strong><span class="ly-pill ly-ok">Ready</span></div>
                            <div class="ly-stock-card"><small>Promo redemptions</small><strong class="ly-stock-number">{{ number_format($issued) }}</strong><span class="ly-pill">Issued</span></div>
                            <div class="ly-stock-card"><small>Value redeemed</small><strong class="ly-stock-number">{{ number_format($redeemed / 100) }}</strong><span class="ly-stock-meta">ETB delivered</span></div>
                        </div>
                        <div class="ly-hint" style="margin-top:16px"><span aria-hidden="true">ⓘ</span><div>Invited users: <strong>{{ number_format($referredUsers) }}</strong>. Only verified, eligible new users produce payable referrals. Figures are based on recorded reward events.</div></div>
                        <div style="margin-top:16px"><a class="ly-action ly-primary" href="{{ \App\Filament\Pages\PromoInventory::getUrl() }}" wire:navigate>Manage inventory →</a></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="ly-split">
        <section class="ly-panel">
            <div class="ly-panel-head"><div><h2>Top referrers</h2><p>Highest qualified referral counts in the selected period</p></div><span class="ly-pill">Live data</span></div>
            <div class="ly-table-wrap">
                <table class="ly-table">
                    <thead><tr><th>Player</th><th>Telegram ID</th><th>Qualified</th><th class="ly-right">Earned</th></tr></thead>
                    <tbody>@forelse($leaders->take(12) as $row)
                    <tr><td><div class="ly-person"><span class="ly-avatar">{{ mb_strtoupper(mb_substr($row->first_name ?: '?',0,1)) }}</span><span class="ly-name">{{ $row->first_name ?: 'Unnamed player' }}</span></div></td>
                        <td class="ly-id">{{ $row->tg_chat_id }}</td><td><span class="ly-pill ly-ok">{{ number_format($row->qualified) }} verified</span></td>
                        <td class="ly-right ly-strong">{{ number_format($row->earned_cents / 100,2) }} ETB</td></tr>
                    @empty<tr><td colspan="4"><div class="ly-empty"><div class="ly-empty-icon">♧</div><strong>No qualifying referrals yet</strong>Referral performance will appear after new members complete verification.</div></td></tr>@endforelse</tbody>
                </table>
            </div>
        </section>
        <section class="ly-panel">
            <div class="ly-panel-head"><div><h2>Daily breakdown</h2><p>Verified referral activity by day</p></div></div>
            <div class="ly-table-wrap">
                <table class="ly-table" style="min-width:360px"><thead><tr><th>Date</th><th class="ly-right">Referrals</th><th class="ly-right">Earned ETB</th></tr></thead>
                <tbody>@forelse($daily->take(12) as $row)
                    <tr><td>{{ \Carbon\Carbon::parse($row->day)->format('d M Y') }}</td><td class="ly-right">{{ number_format($row->referrals) }}</td><td class="ly-right ly-strong">{{ number_format($row->amount_cents/100,2) }}</td></tr>
                @empty<tr><td colspan="3"><div class="ly-empty"><strong>No activity during this period</strong>Try a wider date range.</div></td></tr>@endforelse</tbody></table>
            </div>
        </section>
    </div>
</div>
</x-filament-panels::page>