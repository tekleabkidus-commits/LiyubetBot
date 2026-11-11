<x-filament-panels::page>
<div class="ly-rewards" id="liyubet-reward-settings">
    <section class="ly-hero">
        <div><div class="ly-eyebrow">REWARDS / CONFIGURATION</div><h1>Referral settings</h1><p>Control referral rewards, channel eligibility, available promo redemptions, and every button in the customer-facing Telegram menu.</p></div>
        <a class="ly-action" href="{{ \App\Filament\Pages\ReferralReports::getUrl() }}" wire:navigate>↗ Open analytics</a>
    </section>
    @include('filament.partials.rewards-nav', ['active' => 'settings'])

    <section class="ly-grid ly-grid-3">
        <article class="ly-stat ly-green"><small>Referral bonus</small><span class="ly-symbol">◇</span><strong>{{ number_format((int) \App\Models\Setting::query()->where('key', 'rewards.amount_etb')->first()?->value ?: 2) }} ETB</strong><em>Per eligible verified new member</em></article>
        <article class="ly-stat"><small>Bot language</small><span class="ly-symbol">◎</span><strong>English</strong><em>Customer-facing bot messages</em></article>
        <article class="ly-stat ly-blue"><small>Reward denominations</small><span class="ly-symbol">▣</span><strong>50 / 100 / 500</strong><em>Switch each redemption option below</em></article>
    </section>

    <div class="ly-hint ly-warning"><span>!</span><div><strong>Before enabling promotions:</strong> Set your force-join Channel ID to <strong>-1003512499500</strong> and URL to <strong>https://t.me/liyubet1</strong> in the main <strong>Settings → Infrastructure</strong> page, and confirm @liyubetsbot is an administrator.</div></div>

    <section class="ly-panel">
        <header class="ly-panel-head"><div><h2>Rewards program configuration</h2><p>Owner-only settings. Saving updates the active Telegram bot without re-deploying.</p></div><span class="ly-pill ly-ok">Live settings</span></header>
        <div class="ly-panel-body ly-form-shell">
            <form wire:submit="save">
                {{ $this->form }}
                <div class="ly-form-footer" style="margin-top:22px">
                    <p>Changes apply to future transactions; previously credited rewards are not recalculated. Sensitive bot tokens remain in Laravel Cloud environment variables.</p>
                    <button type="submit" class="ly-action ly-primary" wire:loading.attr="disabled" wire:target="save">✓ Save changes <span wire:loading wire:target="save">…</span></button>
                </div>
            </form>
        </div>
    </section>
</div>
</x-filament-panels::page>