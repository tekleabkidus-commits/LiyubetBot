<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\SettingsService;
use Database\Seeders\SettingsSeeder;

beforeEach(function () {
    $this->seed(SettingsSeeder::class);
});

it('shows the Sport/Casino and Referral/Support menu', function () {
    postWebhook(telegramMessageUpdate(12001, '/start'));
    $keyboard = fakeTelegram()->lastSentTo(12001)['params']['reply_markup'];
    expect($keyboard['inline_keyboard'])->toHaveCount(2)
        ->and($keyboard['inline_keyboard'][0][0]['text'])->toContain('Sport')
        ->and($keyboard['inline_keyboard'][0][1]['text'])->toContain('Casino')
        ->and($keyboard['inline_keyboard'][1][0]['text'])->toContain('Referral')
        ->and($keyboard['inline_keyboard'][1][1]['text'])->toContain('Support');
});

it('shows the personal referral link in English for all language codes', function () {
    postWebhook(telegramMessageUpdate(12001, '/start'));
    $user = User::query()->where('tg_chat_id', 12001)->first();

    postWebhook(telegramCallbackUpdate(12001, 'rewards:referrals', ['language_code' => 'am']));
    $sent = fakeTelegram()->lastSentTo(12001);
    expect($sent['params']['text'])->toContain("https://t.me/LiyubetTestBot?start=ref_{$user->id}")
        ->and($sent['params']['text'])->toContain('Your personal referral link')
        ->and(fakeTelegram()->callsTo('answerCallbackQuery'))->toHaveCount(1);
});

it('declines referral actions when referrals are disabled', function () {
    app(SettingsService::class)->set('features', ['referrals' => false]);
    postWebhook(telegramCallbackUpdate(12001, 'invite:show'));
    expect(fakeTelegram()->nothingSent())->toBeTrue()
        ->and(fakeTelegram()->callsTo('answerCallbackQuery'))->toHaveCount(1);
});

it('attributes a new friend from the referral link once', function () {
    postWebhook(telegramMessageUpdate(12001, '/start'));
    $referrer = User::query()->where('tg_chat_id', 12001)->first();

    postWebhook(telegramMessageUpdate(12002, "/start ref_{$referrer->id}"));
    expect(User::query()->where('tg_chat_id', 12002)->first()->referred_by_user_id)
        ->toBe($referrer->id);
});

it('prevents an existing user from joining a referral link later for credit', function () {
    postWebhook(telegramMessageUpdate(12002, '/start'));
    postWebhook(telegramMessageUpdate(12001, '/start'));
    $referrer = User::query()->where('tg_chat_id', 12001)->first();
    postWebhook(telegramMessageUpdate(12002, "/start ref_{$referrer->id}"));
    expect(User::query()->where('tg_chat_id', 12002)->first()->referred_by_user_id)->toBeNull();
});
