<x-filament-panels::page>
    <div class="flex gap-3 flex-wrap mb-4">
        @foreach([1,7,30,90] as $period)
            <x-filament::button color="{{ $days === $period ? 'primary' : 'gray' }}" wire:click="selectPeriod({{ $period }})">{{ $period }} days</x-filament::button>
        @endforeach
        <x-filament::button color="gray" wire:click="export">Export CSV</x-filament::button>
    </div>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
        @foreach(['Total members' => $totalUsers, 'Invited members' => $referredUsers, 'Qualified referrals' => $qualified, 'All-time rewards (ETB)' => number_format($earned / 100, 2), 'Unspent wallet balances (ETB)' => number_format($walletBalance / 100, 2), 'Promo redemptions' => $issued, 'Redeemed (ETB)' => number_format($redeemed / 100, 2), 'Promo codes available' => $availableCodes] as $label => $value)
            <div class="rounded-xl border border-gray-200 dark:border-gray-700 p-4"><div class="text-sm text-gray-500">{{ $label }}</div><div class="text-2xl font-bold">{{ $value }}</div></div>
        @endforeach
    </div>
    <h2 class="text-lg font-semibold mb-2">Top referrers — last {{ $days }} days</h2>
    <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700 mb-6">
        <table class="w-full text-sm"><thead><tr><th class="p-3 text-left">Player</th><th class="p-3 text-left">Telegram ID</th><th class="p-3 text-right">Qualified</th><th class="p-3 text-right">Earned (ETB)</th></tr></thead>
        <tbody>@forelse($leaders as $row)
        <tr class="border-t border-gray-200 dark:border-gray-700"><td class="p-3">{{ $row->first_name }}</td><td class="p-3">{{ $row->tg_chat_id }}</td><td class="p-3 text-right">{{ $row->qualified }}</td><td class="p-3 text-right">{{ number_format($row->earned_cents/100,2) }}</td></tr>
        @empty<tr><td class="p-3" colspan="4">No qualified referrals during this period.</td></tr>@endforelse</tbody></table>
    </div>
    <h2 class="text-lg font-semibold mb-2">Daily referrals</h2>
    <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
        <table class="w-full text-sm"><thead><tr><th class="p-3 text-left">Date</th><th class="p-3 text-right">Referrals</th><th class="p-3 text-right">Rewards (ETB)</th></tr></thead>
        <tbody>@forelse($daily as $row)<tr class="border-t border-gray-200 dark:border-gray-700"><td class="p-3">{{ $row->day }}</td><td class="p-3 text-right">{{ $row->referrals }}</td><td class="p-3 text-right">{{ number_format($row->amount_cents/100,2) }}</td></tr>
        @empty<tr><td class="p-3" colspan="3">No data yet.</td></tr>@endforelse</tbody></table>
    </div>
</x-filament-panels::page>
