<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Rewards\RewardService;
use App\Services\Rewards\PromoImportService;
use App\Services\SettingsService;
use Database\Seeders\SettingsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->seed(SettingsSeeder::class);
    app(SettingsService::class)->set('rewards.eligible_since', now()->subDay()->toIso8601String());
    app(SettingsService::class)->set('channel.id', -1001234567890);
});

it('credits exactly two birr once to the referrer after a verified channel join', function () {
    $owner = User::factory()->inChannel()->create();
    $newUser = User::factory()->inChannel()->create(['referred_by_user_id' => $owner->id]);
    $rewards = app(RewardService::class);

    expect($rewards->qualify($newUser))->toBeTrue()
        ->and($rewards->qualify($newUser))->toBeFalse();

    expect($rewards->stats($owner))->toMatchArray([
        'balance_cents' => 200,
        'earned_cents' => 200,
        'qualified' => 1,
        'referrals' => 1,
    ]);
    expect(DB::table('referral_rewards')->count())->toBe(1);
    expect(DB::table('reward_ledger')->where('user_id', $owner->id)->count())->toBe(1);
});

it('does not pay for an unverified referral or an older existing user', function () {
    $referrer = User::factory()->create();
    $unverified = User::factory()->create(['referred_by_user_id' => $referrer->id]);
    $oldUser = User::factory()->inChannel()->create([
        'referred_by_user_id' => $referrer->id,
        'joined_at' => now()->subDays(30),
    ]);
    $engine = app(RewardService::class);
    expect($engine->qualify($unverified))->toBeFalse()
        ->and($engine->qualify($oldUser))->toBeFalse()
        ->and($engine->stats($referrer)['balance_cents'])->toBe(0);
});

it('does not deduct money if promo codes are unavailable', function () {
    $user = User::factory()->inChannel()->create();
    $engine = app(RewardService::class);
    $engine->adjust($user, 50, 'Welcome test credit');

    $result = $engine->redeem($user, 50, 'telegram:out-of-stock');
    expect($result['status'])->toBe('no_stock');
    expect($engine->stats($user)['balance_cents'])->toBe(5000);
    expect(DB::table('reward_withdrawals')->count())->toBe(0);
});

it('allocates exactly one encrypted promo code per redemption and preserves request idempotency', function () {
    $user = User::factory()->inChannel()->create();
    $engine = app(RewardService::class);
    $engine->adjust($user, 100, 'Welcome testing credit');

    $inserted = DB::table('promo_codes')->insertGetId([
        'denomination_cents' => 5000,
        'code_hash' => hash('sha256', 'MY-SECRET-CODE-50'),
        'code_ciphertext' => Crypt::encryptString('MY-SECRET-CODE-50'),
        'status' => 'available',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $first = $engine->redeem($user, 50, 'telegram:callback-one');
    $repeated = $engine->redeem($user, 50, 'telegram:callback-one');
    $second = $engine->redeem($user, 50, 'telegram:callback-two');

    expect($first)->toMatchArray(['status' => 'issued', 'code' => 'MY-SECRET-CODE-50', 'amount' => 50])
        ->and($repeated)->toBe($first)
        ->and($second['status'])->toBe('no_stock')
        ->and($engine->stats($user)['balance_cents'])->toBe(5000)
        ->and(DB::table('reward_withdrawals')->count())->toBe(1)
        ->and(DB::table('promo_codes')->where('id', $inserted)->value('status'))->toBe('issued');
    expect($engine->history($user)[0]['code'])->toBe('MY-SECRET-CODE-50');
});

it('allows the owner to freeze a wallet without changing its balance', function () {
    $user = User::factory()->create();
    $engine = app(RewardService::class);
    $engine->adjust($user, 50, 'Temporary test credit');
    $engine->suspend($user->id, 'Suspected duplicate account');
    expect($engine->redeem($user, 50, 'telegram:freeze-test')['status'])->toBe('suspended')
        ->and($engine->stats($user)['balance_cents'])->toBe(5000);
    $engine->suspend($user->id, null, false);
    expect($engine->stats($user)['suspended'])->toBeFalse();
});

it('imports CSV codes, ignores duplicates and rejects invalid values', function () {
    $file = UploadedFile::fake()->createWithContent('promos.csv', "code,amount\nUNUSED-CODE-001,50\nUNUSED-CODE-001,50\nUNUSED-CODE-002,100\n=INJECTION,50\n");
    $stats = app(PromoImportService::class)->import($file, 50, [50, 100, 500]);
    expect($stats['imported'])->toBe(2)
        ->and($stats['duplicates'])->toBe(1)
        ->and($stats['invalid'])->toBe(1)
        ->and(DB::table('promo_codes')->count())->toBe(2);
});

it('renders a two-row menu with Sport, Casino, Referral and Support', function () {
    $buttons = app(\App\Services\Bot\MenuRenderer::class)->rootKeyboard(\App\Enums\BotLanguage::En);
    expect($buttons['inline_keyboard'])->toHaveCount(2)
        ->and($buttons['inline_keyboard'][0][0]['text'])->toContain('Sport')
        ->and($buttons['inline_keyboard'][0][1]['text'])->toContain('Casino')
        ->and($buttons['inline_keyboard'][1][0]['text'])->toContain('Referral')
        ->and($buttons['inline_keyboard'][1][1]['text'])->toContain('Support');
});
