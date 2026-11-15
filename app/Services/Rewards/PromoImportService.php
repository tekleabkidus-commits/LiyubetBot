<?php

declare(strict_types=1);

namespace App\Services\Rewards;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use RuntimeException;
use Throwable;

final class PromoImportService
{
    private const MAX_FILE_BYTES = 10 * 1024 * 1024;
    private const MAX_ROWS = 100000;
    private const INSERT_BATCH_SIZE = 500;

    /**
     * Upload format: first column "code", optional second "amount" (ETB).
     * If amount is absent, the admin-selected denomination is used.
     *
     * @return array{imported: int, duplicates: int, invalid: int, batch: string}
     */
    public function import(UploadedFile $file, int $defaultEtb, array $allowedEtb): array
    {
        $extension = strtolower($file->getClientOriginalExtension());

        if (! in_array($extension, ['xlsx', 'csv'], true)) {
            throw new RuntimeException('Promo upload: Please select an .xlsx or .csv file.');
        }

        // Livewire keeps temporary uploads on its configured disk (often S3 in
        // Laravel Cloud). getRealPath() is then an object key, NOT a local file.
        // OpenSpout's XLSX reader needs a real seekable local path. Always copy
        // the upload stream to a disposable local file before opening it.
        $localPath = $this->copyToLocalTemporaryFile($file);
        $reader = $extension === 'xlsx' ? new XlsxReader : new CsvReader;
        $batch = (string) Str::uuid();
        $stats = ['imported' => 0, 'duplicates' => 0, 'invalid' => 0, 'batch' => $batch];
        $seen = [];
        $pending = [];
        $rows = 0;
        $opened = false;

        try {
            $reader->open($localPath);
            $opened = true;

            // Never leave a partially imported inventory if an XLSX is
            // malformed or the upload hits the maximum row limit.
            DB::beginTransaction();

            try {
                foreach ($reader->getSheetIterator() as $sheet) {
                    $sheetRow = 0;

                    foreach ($sheet->getRowIterator() as $row) {
                        $sheetRow++;
                        $rows++;

                        if ($rows > self::MAX_ROWS) {
                            throw new RuntimeException('Promo upload: Maximum 100,000 spreadsheet rows per import.');
                        }

                        $data = $row->toArray();
                        $code = trim((string) ($data[0] ?? ''));
                        $code = preg_replace('/^\xEF\xBB\xBF/', '', $code) ?? $code;
                        $amountString = trim((string) ($data[1] ?? ''));

                        if ($code === '' && $amountString === '') {
                            continue;
                        }

                        // Support a header on each Excel worksheet and UTF-8
                        // BOMs on CSV exports.
                        if ($sheetRow === 1 && in_array(strtolower($code), ['code', 'promo_code', 'promocode'], true)) {
                            continue;
                        }

                        // Excel can export whole ETB values as "50.0" or
                        // "100.00"; do not silently round fractional amounts.
                        $amount = $amountString === ''
                            ? $defaultEtb
                            : (preg_match('/^\d+(?:\.0+)?$/D', $amountString) ? (int) $amountString : 0);

                        if (str_starts_with(strtoupper($code), 'YOUR-UNUSED-')
                            || str_starts_with(strtoupper($code), 'SAMPLE-')
                            || ! preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{3,127}$/D', $code)
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
                        $pending[] = [
                            'denomination_cents' => $amount * 100,
                            'code_hash' => $hash,
                            'code_ciphertext' => Crypt::encryptString($code),
                            'status' => 'available',
                            'import_batch_id' => $batch,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];

                        if (count($pending) >= self::INSERT_BATCH_SIZE) {
                            $this->storeBatch($pending, $stats);
                        }
                    }
                }

                $this->storeBatch($pending, $stats);
                DB::commit();
            } catch (Throwable $e) {
                DB::rollBack();
                throw $e;
            }
        } finally {
            if ($opened) {
                $reader->close();
            }

            @unlink($localPath);
        }

        return $stats;
    }

    /**
     * @param array<int, array<string, mixed>> $pending
     * @param array{imported: int, duplicates: int, invalid: int, batch: string} $stats
     */
    private function storeBatch(array &$pending, array &$stats): void
    {
        if ($pending === []) {
            return;
        }

        $inserted = (int) DB::table('promo_codes')->insertOrIgnore($pending);
        $stats['imported'] += $inserted;
        $stats['duplicates'] += count($pending) - $inserted;
        $pending = [];
    }

    private function copyToLocalTemporaryFile(UploadedFile $file): string
    {
        $source = $file instanceof TemporaryUploadedFile
            ? $file->readStream()
            : @fopen((string) $file->getRealPath(), 'rb');

        if (! is_resource($source)) {
            throw new RuntimeException('Promo upload: Could not read the uploaded file. Please choose it again.');
        }

        $path = tempnam(sys_get_temp_dir(), 'liyubet-promo-');

        if ($path === false) {
            fclose($source);
            throw new RuntimeException('Promo upload: Server temporary storage is unavailable.');
        }

        try {
            $target = @fopen($path, 'wb');

            if (! is_resource($target)) {
                throw new RuntimeException('Promo upload: Cannot write to server temporary storage.');
            }

            try {
                $copied = stream_copy_to_stream($source, $target, self::MAX_FILE_BYTES + 1);
            } finally {
                fclose($target);
            }

            if ($copied === false || $copied === 0) {
                throw new RuntimeException('Promo upload: The uploaded spreadsheet is empty or unreadable.');
            }

            if ($copied > self::MAX_FILE_BYTES) {
                throw new RuntimeException('Promo upload: File is larger than 10 MB.');
            }

            return $path;
        } catch (Throwable $e) {
            @unlink($path);
            throw $e;
        } finally {
            fclose($source);
        }
    }
}
