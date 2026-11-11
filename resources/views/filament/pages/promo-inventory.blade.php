<x-filament-panels::page>
<div class="ly-rewards" id="liyubet-promo-inventory">
    <section class="ly-hero">
        <div><div class="ly-eyebrow">REWARDS / OPERATIONS</div><h1>Promo-code inventory</h1><p>Upload genuine single-use codes, monitor stock by denomination, and control which codes can be issued to players.</p></div>
        <button type="button" class="ly-action" wire:click="template" wire:loading.attr="disabled" wire:target="template">↓ Download Excel template</button>
    </section>
    @include('filament.partials.rewards-nav', ['active' => 'inventory'])

    <section class="ly-grid ly-grid-4" aria-label="Inventory snapshot">
        @foreach([
          ['All codes',number_format($inventoryTotal),'Uploaded to inventory','▦',''],
          ['Ready to issue',number_format($inventoryAvailable),'Available across all amounts','✓','ly-green'],
          ['Already issued',number_format($inventoryIssued),'Assigned to players','↗','ly-blue'],
          ['Disabled',number_format($inventoryDisabled),'Excluded from allocation','⊘','ly-warm'],
        ] as [$label,$value,$hint,$icon,$color])
            <article class="ly-stat {{ $color }}"><small>{{ $label }}</small><span class="ly-symbol">{{ $icon }}</span><strong>{{ $value }}</strong><em>{{ $hint }}</em></article>
        @endforeach
    </section>
    <div class="ly-split">
        <section class="ly-panel">
            <div class="ly-panel-head"><div><h2>Import new promo codes</h2><p>Bulk upload Excel (.xlsx) or CSV files securely</p></div><span class="ly-pill ly-ok">Encrypted at rest</span></div>
            <div class="ly-panel-body">
                <div class="ly-hint"><span>ⓘ</span><div><strong>Format:</strong> Column A = <code>code</code>, column B = <code>amount</code> (optional ETB value). Use real unused promo codes. Invalid, duplicate and sample codes are automatically skipped.</div></div>
                <form wire:submit="upload" enctype="multipart/form-data" style="margin-top:20px">
                    <div class="ly-form-row">
                        <div class="ly-field">
                            <label for="ly-default-value">Default value</label>
                            <select id="ly-default-value" class="ly-input" wire:model="denomination" aria-label="Default promo code denomination">
                                <option value="50">50 ETB</option><option value="100">100 ETB</option><option value="500">500 ETB</option>
                            </select>
                        </div>
                        <div class="ly-field ly-wide">
                            <label for="ly-sheet">Select your spreadsheet</label>
                            <input id="ly-sheet" class="ly-input" type="file" wire:model="spreadsheet" accept=".xlsx,.csv" aria-describedby="ly-upload-limit">
                        </div>
                    </div>
                    @error('denomination')<div class="ly-form-error" role="alert" style="margin-top:8px">{{ $message }}</div>@enderror
                    @error('spreadsheet')<div class="ly-form-error" role="alert" style="margin-top:8px">{{ $message }}</div>@enderror
                    <div id="ly-upload-limit" class="ly-small" style="margin:12px 0">Up to 10 MB per file. Existing issued codes are never overwritten.</div>
                    <div class="ly-form-footer">
                        <p>Upload is restricted to the Owner. New codes will appear in the stock table after a successful import.</p>
                        <button type="submit" class="ly-action ly-primary" wire:loading.attr="disabled" wire:target="upload,spreadsheet">↑ Import promo codes <span wire:loading wire:target="upload,spreadsheet">…</span></button>
                    </div>
                </form>
                @if($lastImport)<div class="ly-hint ly-success" role="status" style="margin-top:14px"><span>✓</span><div>{{ $lastImport }}</div></div>@endif
            </div>
        </section>
        <section class="ly-panel">
            <div class="ly-panel-head"><div><h2>Available stock</h2><p>Current codes by redemption amount</p></div></div>
            <div class="ly-panel-body">
                @foreach([50,100,500] as $amount)
                    @php
                        $denomRecords = $stock->where('denomination_cents',$amount*100);
                        $available = (int)$denomRecords->where('status','available')->sum('count');
                        $all = (int)$denomRecords->sum('count');
                        $pct = $all > 0 ? 100 * $available/$all : 0;
                    @endphp
                    <div style="margin-bottom:18px">
                        <div class="ly-inline-flex" style="justify-content:space-between;margin-bottom:9px"><span class="ly-strong">{{ $amount }} ETB codes</span><span class="ly-small">{{ number_format($available) }} available / {{ number_format($all) }} total</span></div>
                        <div class="ly-progress"><div class="ly-progress-track"><span style="width:{{ $pct }}%"></span></div><strong>{{ round($pct) }}%</strong></div>
                    </div>
                @endforeach
                <div class="ly-hint ly-warning"><span>!</span><div>Out of stock? Users see a wait message and <strong>their balance remains unchanged</strong>. Refill stock to continue issuing.</div></div>
            </div>
        </section>
    </div>
    <section class="ly-panel">
        <div class="ly-panel-head">
            <div><h2>Inventory ledger</h2><p>Search, filter, and manage uploaded promo code records</p></div>
            <div class="ly-tools">
                <form wire:submit="searchInventory" class="ly-inline-flex">
                    <input class="ly-input" style="width:175px;min-height:36px" type="search" wire:model="searchTerm" placeholder="ID or Telegram ID" aria-label="Search inventory">
                    <button type="submit" class="ly-action ly-xs">Search</button>
                </form>
                <div class="ly-segment" aria-label="Stock status">
                    @foreach(['all' => 'All','available' => 'Ready','issued' => 'Issued','disabled' => 'Disabled'] as $status=>$label)
                        <button type="button" wire:click="setFilter('{{ $status }}')" class="{{ $filter === $status ? 'is-active' : '' }}" aria-pressed="{{ $filter === $status ? 'true' : 'false' }}">{{ $label }}</button>
                    @endforeach
                </div>
            </div>
        </div>
        <div class="ly-table-wrap">
            <table class="ly-table">
                <thead><tr><th>Inventory ID</th><th>Value</th><th>Status</th><th>Assigned to</th><th>Imported on</th><th>Actions</th></tr></thead>
                <tbody>@forelse($codes as $code)
                    <tr>
                        <td class="ly-id">#{{ $code->id }}</td><td><strong class="ly-name">{{ number_format($code->denomination_cents/100) }} ETB</strong></td>
                        <td><span class="ly-pill {{ $code->status === 'available' ? 'ly-ok' : ($code->status === 'disabled' ? 'ly-bad' : 'ly-review') }}">{{ ucfirst($code->status) }}</span></td>
                        <td>{{ $code->tg_chat_id ?? '—' }}</td>
                        <td>{{ \Carbon\Carbon::parse($code->created_at)->format('d M Y · H:i') }}</td>
                        <td>
                            @if($code->status === 'available')
                                <button type="button" class="ly-action ly-danger ly-xs" wire:click="setStatus({{ $code->id }}, 'disabled')" wire:confirm="Disable this unused promo code?">Disable</button>
                            @elseif($code->status === 'disabled')
                                <button type="button" class="ly-action ly-success ly-xs" wire:click="setStatus({{ $code->id }}, 'available')">Enable</button>
                            @else <span class="ly-small">Allocated · locked</span> @endif
                        </td>
                    </tr>
                @empty<tr><td colspan="6"><div class="ly-empty"><div class="ly-empty-icon">▤</div><strong>No codes to display</strong>Upload a valid spreadsheet, or change the search and filter.</div></td></tr>@endforelse</tbody>
            </table>
        </div>
        <div class="ly-pagination">{{ $codes->links() }}</div>
    </section>
</div>
</x-filament-panels::page>