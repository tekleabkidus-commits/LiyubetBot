<?php

declare(strict_types=1);

namespace App\Services\Rewards;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use RuntimeException;

final class PromoImportService
{
    /**
     * Upload format: first column "code", optional second "amount" (ETB).
     * If amount is absent, the admin-selected denomination is used.
     * @return array{imported: int, duplicates: int, invalid: int, batch: string}
     */
    public function import(UploadedFile $file, int $defaultEtb, array $allowedEtb): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        if (! in_array($extension, ['xlsx', 'csv'], true)) {
            throw new RuntimeException('Only .xlsx or .csv files are accepted.');
        }

        $reader = $extension === 'xlsx' ? new XlsxReader : new CsvReader;
        $batch = (string) Str::uuid();
        $stats = ['imported' => 0, 'duplicates' => 0, 'invalid' => 0, 'batch' => $batch];
        $seen = [];
        $rows = 0;

        try {
            $reader->open($file->getRealPath());
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    if (++$rows > 100000) {
                        throw new RuntimeException('Maximum 100,000 rows per upload.');
                    }

                    $data = $row->toArray();
                    $code = trim((string) ($data[0] ?? ''));
                    $amountString = trim((string) ($data[1] ?? ''));
                    if ($code === '' && $amountString === '') {
                        continue;
                    }
                    if ($rows === 1 && in_array(strtolower($code), ['code', 'promo_code', 'promocode'], true)) {
                        continue;
                    }
                    $amount = $amountString === '' ? $defaultEtb : (ctype_digit($amountString) ? (int) $amountString : 0);
                    if (! preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{3,127}$/D', $code)
                        || ! in_array($amount, $allowedEtb, true)) {
                        $stats['invalid']++;
                        continue;
                    }
                    $hash = hash('sha256', strtoupper($code));
                    if (isset($seen[$hash])) {
                        $stats['duplicates']++;
                        continue;
                    }
                    $seen[$hash] = true;

                    $inserted = DB::table('promo_codes')->insertOrIgnore([
                        'denomination_cents' => $amount * 100,
                        'code_hash' => $hash,
                        'code_ciphertext' => Crypt::encryptString($code),
                        'status' => 'available',
                        'import_batch_id' => $batch,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $inserted ? $stats['imported']++ : $stats['duplicates']++;
                }
            }
        } finally {
            $reader->close();
        }

        return $stats;
    }
}
