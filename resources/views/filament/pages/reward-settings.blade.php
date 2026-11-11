<x-filament-panels::page>
    <div class="rounded-xl border border-gray-200 dark:border-gray-700 p-5 mb-4">
        <h2 class="text-xl font-semibold">Referral &amp; promo-code configuration</h2>
        <p>All changes apply to the live bot. Referral reward amounts are captured when each referral qualifies; previous rewards are not recalculated.</p>
        <p class="mt-2">Set the channel ID and URL in <strong>Settings</strong>. Keep channel verification enabled before announcing the promotion.</p>
    </div>
    <form wire:submit="save" class="space-y-5">
        {{ $this->form }}
        <x-filament::button type="submit" icon="heroicon-o-check">Save reward settings</x-filament::button>
    </form>
</x-filament-panels::page>
