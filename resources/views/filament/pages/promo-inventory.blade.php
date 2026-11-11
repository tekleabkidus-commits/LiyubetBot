<x-filament-panels::page>
    <h2 class="text-xl font-semibold">Single-use promo-code inventory</h2>
    <p class="text-sm mb-4">Upload CSV or Excel (.xlsx) files. The first column contains the code; the optional second column is the amount in ETB. Every unique code can be issued only once. Codes are encrypted at rest, and never displayed in inventory lists.</p>
    <form wire:submit="upload" class="rounded-xl border border-gray-200 dark:border-gray-700 p-5 space-y-4 mb-6" enctype="multipart/form-data">
        <h3 class="text-lg font-semibold">Upload promo codes</h3>
        <div class="flex flex-wrap gap-3 items-center">
            <label>Default denomination
                <select wire:model="denomination" class="rounded-lg border p-2 bg-transparent">
                    <option value="50">50 ETB</option><option value="100">100 ETB</option><option value="500">500 ETB</option>
                </select>
            </label>
            <label>Excel or CSV file <input type="file" wire:model="spreadsheet" accept=".xlsx,.csv" class="block mt-2"/></label>
        </div>
        @error('spreadsheet')<p class="text-red-600">{{ $message }}</p>@enderror
        @error('denomination')<p class="text-red-600">{{ $message }}</p>@enderror
        <div class="flex gap-3 flex-wrap">
            <x-filament::button type="submit" wire:loading.attr="disabled" wire:target="upload,spreadsheet">Import codes</x-filament::button>
            <x-filament::button color="gray" wire:click="template" type="button">Download XLSX example</x-filament::button>
        </div>
        @if($lastImport)<div class="text-green-600">{{ $lastImport }}</div>@endif
    </form>
    <h3 class="text-lg font-semibold mb-2">Stock by denomination</h3>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
        @forelse($stock as $s)<div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
            <div>{{ $s->denomination_cents / 100 }} ETB / {{ ucfirst($s->status) }}</div>
            <div class="text-2xl font-bold">{{ $s->count }}</div>
        </div>@empty<p>No promo codes imported yet.</p>@endforelse
    </div>
    <div class="flex flex-wrap gap-2 mb-3">
        @foreach(['all','available','issued','disabled'] as $s)<x-filament::button color="{{ $filter === $s ? 'primary' : 'gray' }}" wire:click="setFilter('{{ $s }}')">{{ ucfirst($s) }}</x-filament::button>@endforeach
    </div>
    <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
        <table class="w-full text-sm"><thead><tr><th class="p-3 text-left">ID</th><th class="p-3 text-left">Amount</th><th class="p-3 text-left">Status</th><th class="p-3 text-left">Issued to Telegram ID</th><th class="p-3 text-left">Imported</th><th class="p-3 text-left">Actions</th></tr></thead>
        <tbody>@forelse($codes as $code)<tr class="border-t border-gray-200 dark:border-gray-700">
            <td class="p-3">#{{ $code->id }}</td><td class="p-3">{{ $code->denomination_cents / 100 }} ETB</td><td class="p-3">{{ ucfirst($code->status) }}</td>
            <td class="p-3">{{ $code->tg_chat_id ?? '—' }}</td><td class="p-3">{{ $code->created_at }}</td>
            <td class="p-3">
                @if($code->status === 'available')<x-filament::button color="danger" size="sm" wire:click="setStatus({{ $code->id }}, 'disabled')" wire:confirm="Disable this unused code?">Disable</x-filament::button>
                @elseif($code->status === 'disabled')<x-filament::button color="success" size="sm" wire:click="setStatus({{ $code->id }}, 'available')">Enable</x-filament::button>
                @else<em>Already allocated</em>@endif
            </td>
        </tr>@empty<tr><td colspan="6" class="p-3">No codes match this filter.</td></tr>@endforelse</tbody></table>
    </div>
    <p class="text-xs mt-3">Showing the 100 newest codes. Filtering does not delete records.</p>
</x-filament-panels::page>
