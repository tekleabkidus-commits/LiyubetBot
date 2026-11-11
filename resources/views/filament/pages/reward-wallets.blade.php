<x-filament-panels::page>
    <div class="rounded-xl border border-gray-200 dark:border-gray-700 p-4 mb-4">
        <h2 class="text-xl font-semibold">Referral wallets and fraud controls</h2>
        <p class="text-sm">Balances are tracked in cents with an immutable ledger of referrals, redemptions and manual adjustments. Suspended wallets cannot earn new rewards or redeem promo codes.</p>
    </div>
    <form wire:submit="search" class="flex flex-wrap items-end gap-2 mb-4">
        <label>Search Telegram ID, player name or username
            <input type="text" wire:model="query" maxlength="80" placeholder="Search players" class="block rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent p-2"/>
        </label>
        <x-filament::button type="submit">Search</x-filament::button>
    </form>
    <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700 mb-5">
        <table class="w-full text-sm"><thead><tr><th class="p-3 text-left">Player</th><th class="p-3 text-left">Telegram ID</th><th class="p-3 text-right">Available ETB</th><th class="p-3 text-right">Earned ETB</th><th class="p-3 text-right">Redeemed ETB</th><th class="p-3 text-left">Controls</th></tr></thead>
        <tbody>@forelse($wallets as $wallet)<tr class="border-t border-gray-200 dark:border-gray-700">
            <td class="p-3">{{ $wallet->first_name }} @if($wallet->username)(@{{ $wallet->username }})@endif</td>
            <td class="p-3">{{ $wallet->tg_chat_id }}</td>
            <td class="p-3 text-right">{{ number_format($wallet->balance_cents / 100, 2) }}</td>
            <td class="p-3 text-right">{{ number_format($wallet->earned_cents / 100, 2) }}</td>
            <td class="p-3 text-right">{{ number_format($wallet->redeemed_cents / 100, 2) }}</td>
            <td class="p-3 flex gap-2">
                <x-filament::button size="sm" wire:click="selectUser({{ $wallet->user_id }})">Review ledger</x-filament::button>
                @if($wallet->suspended_at)<x-filament::button size="sm" color="success" wire:click="freeze({{ $wallet->user_id }}, false)">Unfreeze</x-filament::button>
                @else<x-filament::button size="sm" color="danger" wire:click="freeze({{ $wallet->user_id }})" wire:confirm="Suspend this wallet?">Freeze</x-filament::button>@endif
            </td>
        </tr>@empty<tr><td colspan="6" class="p-3">No wallet records found.</td></tr>@endforelse</tbody></table>
    </div>
    @if($selectedUser)
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 p-5 mb-5">
            <h3 class="text-lg font-semibold">Adjust wallet for player #{{ $selectedUser }}</h3>
            <p class="text-sm mb-3">Use a positive ETB amount to credit or a negative ETB amount to deduct. A reason is required; this action is audited.</p>
            <form wire:submit="adjust" class="flex flex-wrap gap-3 items-end">
                <label>Change (ETB)<input type="number" min="-10000" max="10000" step="1" wire:model="adjustmentEtb" class="block rounded-lg border p-2 bg-transparent"/></label>
                <label>Reason<input type="text" minlength="8" maxlength="250" wire:model="adjustmentReason" class="block rounded-lg border p-2 bg-transparent"/></label>
                <x-filament::button type="submit" color="warning" wire:confirm="Apply this permanent wallet adjustment?">Save adjustment</x-filament::button>
            </form>
            @error('adjustmentEtb')<p class="text-red-600">{{ $message }}</p>@enderror
            @error('adjustmentReason')<p class="text-red-600">{{ $message }}</p>@enderror
        </div>
        <h3 class="text-lg font-semibold mb-2">Recent wallet ledger — player #{{ $selectedUser }}</h3>
        <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
            <table class="w-full text-sm"><thead><tr><th class="p-3 text-left">Date</th><th class="p-3 text-left">Type</th><th class="p-3 text-right">Change ETB</th><th class="p-3 text-right">Balance ETB</th></tr></thead>
            <tbody>@forelse($ledger as $entry)<tr class="border-t border-gray-200 dark:border-gray-700"><td class="p-3">{{ $entry->created_at }}</td><td class="p-3">{{ $entry->entry_type }}</td><td class="p-3 text-right">{{ number_format($entry->change_cents/100,2) }}</td><td class="p-3 text-right">{{ number_format($entry->balance_after_cents/100,2) }}</td></tr>
            @empty<tr><td colspan="4" class="p-3">No transactions.</td></tr>@endforelse</tbody></table>
        </div>
    @endif
</x-filament-panels::page>
