<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Anchor reward eligibility at deployment. Older users may keep
        // their attribution for reporting but cannot earn retroactive money.
        DB::table('settings')->insertOrIgnore([
            'key' => 'rewards.eligible_since',
            'value' => json_encode(now()->toIso8601String()),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // The eligibility cutoff must survive rollbacks to prevent retroactive payouts.
    }
};
