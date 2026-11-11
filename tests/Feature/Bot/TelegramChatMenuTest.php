<?php

declare(strict_types=1);

use App\Services\Bot\MenuRenderer;
use App\Services\SettingsService;
use App\Enums\BotLanguage;
use App\Models\User;
use Database\Seeders\SettingsSeeder;

beforeEach(function () {
    $this->seed(SettingsSeeder::class);
    app(SettingsService::class)->set('channel.id', -1003512499500);
    app(SettingsService::class)->set('channel.url', 'https://t.me/liyubet1');
});

it('displays a persistent reply keyboard next to the chat input after /start', function () {
    fakeTelegram()->setMembership(9201, 'member');

    postWebhook(telegramMessageUpdate(9201, '/start'));

    $sent = fakeTelegram()->lastSentTo(9201);
    $keyboard = $sent['params']['reply_markup'];

    expect($keyboard['is_persistent'])->toBeTrue()
        ->and($keyboard['resize_keyboard'])->toBeTrue()
        ->and($keyboard['keyboard'][0][0]['text'])->toContain('Sport')
        ->and($keyboard['keyboard'][0][1]['text'])->toContain('Casino')
        ->and($keyboard['keyboard'][1][0]['text'])->toContain('Referral')
        ->and($keyboard['keyboard'][1][1]['text'])->toContain('Support')
        ->and($keyboard['keyboard'][2][0]['text'])->toContain('Start');
});

it('treats a reply keyboard button as a business action, not a support message', function () {
    fakeTelegram()->setMembership(9201, 'member');
    postWebhook(telegramMessageUpdate(9201, '/start'));
    postWebhook(telegramMessageUpdate(9201, '🎁 Referral'));

    expect(fakeTelegram()->lastSentTo(9201)['params']['text'])
        ->toContain('Referral Rewards');

    postWebhook(telegramMessageUpdate(9201, '💰 Balance'));
    expect(fakeTelegram()->lastSentTo(9201)['params']['text'])
        ->toContain('Referral Wallet');
});

it('offers the command menu next to the chat field', function () {
    $this->artisan('telegram:setup-menu')->assertSuccessful();
    expect(fakeTelegram()->callsTo('setMyCommands'))->toHaveCount(1)
        ->and(fakeTelegram()->callsTo('setChatMenuButton'))->toHaveCount(1);
});

it('rejects keyboard actions before the user verifies channel membership', function () {
    fakeTelegram()->setMembership(9201, 'left');
    postWebhook(telegramMessageUpdate(9201, '💰 Balance'));
    expect(fakeTelegram()->lastSentTo(9201)['params']['text'])
        ->toContain('join our channel');
});

it('accepts configured button labels and slash commands', function () {
    $menu = app(MenuRenderer::class);
    app(SettingsService::class)->set('rewards.menu_sport_label', '⚽ Football');
    expect($menu->actionFromText('⚽ Football'))->toBe('sport')
        ->and($menu->actionFromText('🏠 Start'))->toBe('home')
        ->and($menu->actionFromText('/referral'))->toBe('referral')
        ->and($menu->actionFromText('/balance'))->toBe('balance')
        ->and($menu->actionFromText('Hello, I need support'))->toBeNull();
});
