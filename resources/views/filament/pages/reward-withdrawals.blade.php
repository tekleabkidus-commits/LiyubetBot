<x-filament-panels::page>
    <h2 class="text-xl font-semibold">Promo-code redemption history</h2>
    @if($revealedCode)
        <div class="rounded-xl border border-yellow-400 bg-yellow-50 dark:bg-yellow-950 p-4 mb-4">
            <p><strong>Private promo code for withdrawal #{{ $revealedWithdrawalId }}</strong></p>
            <code class="break-all">{{ $revealedCode }}</code>
            <div><x-filament::button color="gray" size="sm" wire:click="hideCode">Hide code</x-filament::button></div>
        </div>
    @endif
    <p class="mb-4">Promo codes are issued once and cannot safely be returned to stock after delivery. Flagging a redemption does not return funds or reactivate its code.</p>
    <div class="grid grid-cols-3 gap-3 mb-5">
        <div class="rounded-xl p-4 border border-gray-200 dark:border-gray-700"><div>Withdrawals</div><strong class="text-2xl">{{ $totalIssued }}</strong></div>
        <div class="rounded-xl p-4 border border-gray-200 dark:border-gray-700"><div>Redeemed ETB</div><strong class="text-2xl">{{ number_format($totalEtb, 2) }}</strong></div>
        <div class="rounded-xl p-4 border border-gray-200 dark:border-gray-700"><div>Flagged</div><strong class="text-2xl">{{ $flagged }}</strong></div>
    </div>
    <div class="flex flex-wrap gap-2 mb-4">
        @foreach(['all','issued','flagged'] as $s)<x-filament::button color="{{ $filter === $s ? 'primary' : 'gray' }}" wire:click="setFilter('{{ $s }}')">{{ ucfirst($s) }}</x-filament::button>@endforeach
        <x-filament::button color="gray" wire:click="export">Export all withdrawals (CSV)</x-filament::button>
    </div>
    <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
        <table class="w-full text-sm"><thead><tr><th class="p-3 text-left">ID</th><th class="p-3 text-left">Player</th><th class="p-3 text-left">Telegram ID</th><th class="p-3 text-right">Amount</th><th class="p-3 text-left">Promo inventory ID</th><th class="p-3 text-left">Date</th><th class="p-3 text-left">Status / Review</th></tr></thead>
        <tbody>@forelse($withdrawals as $w)<tr class="border-t border-gray-200 dark:border-gray-700">
            <td class="p-3">{{ $w->id }}</td><td class="p-3">{{ $w->first_name }}</td><td class="p-3">{{ $w->tg_chat_id }}</td><td class="p-3 text-right">{{ $w->amount_cents / 100 }} ETB</td>
            <td class="p-3">{{ $w->promo_code_id }} <x-filament::button color="gray" size="sm" wire:click="reveal({{ $w->id }})">Reveal</x-filament::button></td><td class="p-3">{{ $w->created_at }}</td>
            <td class="p-3">@if($w->status === 'issued')<x-filament::button color="warning" size="sm" wire:click="flag({{ $w->id }})">Flag for review</x-filament::button>
            @else<x-filament::button size="sm" wire:click="flag({{ $w->id }}, false)">Clear flag</x-filament::button>@endif</td>
        </tr>@empty<tr><td colspan="7" class="p-3">No withdrawals yet.</td></tr>@endforelse</tbody></table>
    </div>
    <p class="text-xs mt-3">Showing the newest 100 withdrawals. Export includes every record.</p>
</x-filament-panels::page>
