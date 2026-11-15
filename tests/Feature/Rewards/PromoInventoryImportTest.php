<?php

declare(strict_types=1);

use App\Services\Rewards\PromoImportService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

it('imports real Excel XLSX promo codes with whole-number spreadsheet amounts', function () {
    $path = tempnam(sys_get_temp_dir(), 'promo-test-').'.xlsx';
    $writer = new Writer;
    $writer->openToFile($path);
    $writer->addRow(Row::fromValues(['code', 'amount']));
    $writer->addRow(Row::fromValues(['EXCEL-PROMO-0001', 50]));
    $writer->addRow(Row::fromValues(['EXCEL-PROMO-0002', 100.0]));
    $writer->addRow(Row::fromValues(['EXCEL-PROMO-0003', 500]));
    $writer->close();

    try {
        $file = new UploadedFile($path, 'promos.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
        $result = app(PromoImportService::class)->import($file, 50, [50, 100, 500]);

        expect($result['imported'])->toBe(3)
            ->and($result['duplicates'])->toBe(0)
            ->and(DB::table('promo_codes')->where('status', 'available')->count())->toBe(3);

        $encrypted = DB::table('promo_codes')->where('denomination_cents', 10000)->value('code_ciphertext');
        expect(Crypt::decryptString($encrypted))->toBe('EXCEL-PROMO-0002');
    } finally {
        @unlink($path);
    }
});

it('reads a Livewire temporary upload stream rather than the local path, including S3-backed uploads', function () {
    Storage::fake('s3');
    $csv = "code,amount\nREMOTE-PROMO-0001,50\nREMOTE-PROMO-0002,100\n";
    $original = UploadedFile::fake()->createWithContent('promos.csv', $csv);
    $filename = TemporaryUploadedFile::generateHashNameWithOriginalNameEmbedded($original);
    Storage::disk('s3')->put('livewire-tmp/'.$filename, $csv);

    // Simulate an object-storage path which cannot be opened as a local file.
    $file = new class($filename, 's3') extends TemporaryUploadedFile {
        public function getRealPath(): string
        {
            return '/missing/s3-object-key-does-not-exist';
        }
    };

    $result = app(PromoImportService::class)->import($file, 50, [50, 100]);

    expect($result['imported'])->toBe(2)
        ->and($result['invalid'])->toBe(0)
        ->and(DB::table('promo_codes')->count())->toBe(2);
});

it('handles CSV BOMs and skips duplicate and placeholder promo codes', function () {
    $file = UploadedFile::fake()->createWithContent(
        'promos.csv',
        "\xEF\xBB\xBFcode,amount\nREDEEM-VALID-001,50.00\nREDEEM-VALID-001,50\nYOUR-UNUSED-PROMO-002,100\n"
    );

    $result = app(PromoImportService::class)->import($file, 50, [50, 100]);

    expect($result['imported'])->toBe(1)
        ->and($result['duplicates'])->toBe(1)
        ->and($result['invalid'])->toBe(1)
        ->and(DB::table('promo_codes')->count())->toBe(1);
});

it('rolls back already-inserted promo batches if a later row fails', function () {
    $content = "code,amount\n";
    for ($i = 1; $i <= 501; $i++) {
        $content .= sprintf("BULK-TEST-%06d,50\n", $i);
    }
    $file = UploadedFile::fake()->createWithContent('promos.csv', $content);

    $calls = 0;
    Crypt::shouldReceive('encryptString')->andReturnUsing(function (string $code) use (&$calls): string {
        if (++$calls === 501) {
            throw new RuntimeException('Simulated failure after the first batch');
        }

        return 'test-encrypted-content';
    });

    expect(fn () => app(PromoImportService::class)->import($file, 50, [50]))
        ->toThrow(RuntimeException::class, 'Simulated failure');

    expect(DB::table('promo_codes')->count())->toBe(0);
});


it('uses a non-reserved Livewire import action for promo spreadsheet submission', function () {
    expect(method_exists(\App\Filament\Pages\PromoInventory::class, 'importPromoCodes'))->toBeTrue()
        ->and(method_exists(\App\Filament\Pages\PromoInventory::class, 'upload'))->toBeFalse();

    $view = file_get_contents(resource_path('views/filament/pages/promo-inventory.blade.php'));
    expect($view)->toContain('wire:submit="importPromoCodes"')
        ->and($view)->not->toContain('wire:submit="upload"');
});
