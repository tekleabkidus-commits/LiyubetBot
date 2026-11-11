<?php

namespace App\Filament\Pages;

use App\Services\AuditLogger;
use App\Services\Rewards\PromoImportService;
use App\Services\Rewards\RewardService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Livewire\WithFileUploads;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

class PromoInventory extends Page
{
    use WithFileUploads;

    protected string $view = 'filament.pages.promo-inventory';
    protected static ?string $navigationLabel = 'Promo Codes';
    protected static string|\UnitEnum|null $navigationGroup = 'Rewards';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-ticket';
    protected static ?int $navigationSort = 2;
    public $spreadsheet;
    public int $denomination = 50;
    public string $filter = 'all';
    public string $lastImport = '';

    public static function canAccess(): bool
    {
        return auth()->user()?->isOwner() === true;
    }

    public function upload(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->validate([
            'spreadsheet' => ['required', 'file', 'mimes:xlsx,csv,txt', 'max:10240'],
            'denomination' => ['required', 'integer', 'in:50,100,500'],
        ]);
        $allowed = app(RewardService::class)->denominations();
        if (! in_array($this->denomination, $allowed, true)) {
            $this->addError('denomination', 'This denomination is disabled in Reward Settings.');
            return;
        }
        try {
            $result = app(PromoImportService::class)->import($this->spreadsheet, $this->denomination, $allowed);
        } catch (\Throwable $e) {
            report($e);
            $this->addError('spreadsheet', 'Import failed. Verify the file is a valid XLSX or CSV and try again.');
            return;
        }

        app(AuditLogger::class)->log('rewards.codes.imported', null, [
            'batch' => $result['batch'], 'imported' => $result['imported'],
            'duplicates' => $result['duplicates'], 'invalid' => $result['invalid'],
        ]);
        $this->lastImport = "Imported {$result['imported']} codes. Skipped {$result['duplicates']} duplicates and {$result['invalid']} invalid rows.";
        $this->reset('spreadsheet');
        Notification::make()->title('Promo code import complete')->body($this->lastImport)->success()->send();
    }

    public function setFilter(string $value): void
    {
        abort_unless(in_array($value, ['all', 'available', 'issued', 'disabled'], true), 422);
        $this->filter = $value;
    }

    public function setStatus(int $id, string $status): void
    {
        abort_unless(static::canAccess(), 403);
        abort_unless(in_array($status, ['disabled', 'available'], true), 422);
        $original = $status === 'disabled' ? 'available' : 'disabled';
        $updated = DB::table('promo_codes')->where('id', $id)->where('status', $original)
            ->update(['status' => $status, 'updated_at' => now()]);
        if ($updated) {
            app(AuditLogger::class)->log('rewards.code.status_changed', null, ['code_id' => $id, 'to' => $status]);
            Notification::make()->title('Promo code updated')->success()->send();
        }
    }

    public function template(): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        abort_unless(static::canAccess(), 403);
        $path = tempnam(sys_get_temp_dir(), 'liyubet-promo-').'.xlsx';
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(['code', 'amount']));
        $writer->addRow(Row::fromValues(['YOUR-UNUSED-PROMO-001', 50]));
        $writer->addRow(Row::fromValues(['YOUR-UNUSED-PROMO-002', 100]));
        $writer->addRow(Row::fromValues(['YOUR-UNUSED-PROMO-003', 500]));
        $writer->close();

        return response()->download($path, 'liyubet-promo-template.xlsx')->deleteFileAfterSend(true);
    }

    protected function getViewData(): array
    {
        $stock = DB::table('promo_codes')->select('denomination_cents', 'status')
            ->selectRaw('count(*) as count')->groupBy('denomination_cents', 'status')
            ->orderBy('denomination_cents')->get();
        $codes = DB::table('promo_codes as p')
            ->leftJoin('users as u', 'u.id', '=', 'p.issued_to_user_id')
            ->when($this->filter !== 'all', fn ($q) => $q->where('p.status', $this->filter))
            ->select('p.id', 'p.denomination_cents', 'p.status', 'p.import_batch_id', 'p.issued_at', 'p.created_at', 'u.tg_chat_id')
            ->orderByDesc('p.id')->limit(100)->get();
        return compact('stock', 'codes');
    }
}
