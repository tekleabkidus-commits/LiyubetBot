<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referral_wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('balance_cents')->default(0);
            $table->unsignedBigInteger('earned_cents')->default(0);
            $table->unsignedBigInteger('redeemed_cents')->default(0);
            $table->timestamp('suspended_at')->nullable();
            $table->string('suspension_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('referral_rewards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referred_user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->foreignId('referrer_user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('amount_cents');
            $table->timestamp('qualified_at');
            $table->timestamps();
            $table->index(['referrer_user_id', 'qualified_at']);
        });

        Schema::create('promo_codes', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('denomination_cents');
            $table->char('code_hash', 64)->unique();
            $table->text('code_ciphertext');
            $table->string('status', 20)->default('available');
            $table->foreignId('issued_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('issued_at')->nullable();
            $table->uuid('import_batch_id')->nullable();
            $table->timestamps();
            $table->index(['denomination_cents', 'status']);
        });

        Schema::create('reward_withdrawals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('promo_code_id')->unique()->constrained('promo_codes')->restrictOnDelete();
            $table->string('request_key', 120)->unique();
            $table->unsignedInteger('amount_cents');
            $table->string('status', 20)->default('issued');
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('reward_ledger', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('entry_type', 30);
            $table->bigInteger('change_cents');
            $table->unsignedBigInteger('balance_after_cents');
            $table->unsignedBigInteger('related_id')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reward_ledger');
        Schema::dropIfExists('reward_withdrawals');
        Schema::dropIfExists('promo_codes');
        Schema::dropIfExists('referral_rewards');
        Schema::dropIfExists('referral_wallets');
    }
};
