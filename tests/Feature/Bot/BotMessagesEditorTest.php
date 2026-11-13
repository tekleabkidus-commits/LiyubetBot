<?php

declare(strict_types=1);

use App\Filament\Pages\BotMessages;
use App\Models\Admin;
use App\Models\MediaFile;
use App\Models\User;
use App\Services\Bot\BotMessageCatalog;
use App\Services\Bot\BotMessageService;
use App\Services\SettingsService;
use Database\Seeders\SettingsSeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(SettingsSeeder::class);
    app(SettingsService::class)->set('channel.id', -1003512499500);
    app(SettingsService::class)->set('channel.url', 'https://t.me/liyubet1');
});

it('preserves existing welcome copy and keeps the proposed second message disabled', function () {
    $service = app(BotMessageService::class);
    $user = User::factory()->inChannel()->create(['first_name' => 'Kidus']);

    $content = $service->content('welcome');
    expect($content['text'])->toContain('Welcome to Liyubet, {first_name}!')
        ->and($content['text'])->toContain('{referral_link}')
        ->and($service->enabled('referral_promo'))->toBeFalse();

    $result = app(\App\Services\Bot\WelcomeService::class)->sendWelcome($user);

    expect($result->successful())->toBeTrue()
        ->and(fakeTelegram()->sentTo($user->tg_chat_id))->toHaveCount(1)
        ->and(fakeTelegram()->lastSentTo($user->tg_chat_id)['params']['text'])
        ->toContain('Welcome to Liyubet, Kidus!');
});

it('sends independently edited welcome and a second referral promotion when enabled', function () {
    $user = User::factory()->inChannel()->create(['first_name' => 'Kidus']);
    $editor = app(BotMessageService::class);
    $editor->set('welcome', ['text' => "እንኳን ደህና መጡ, {first_name}!\nWelcome to Liyubet.", 'media_file_id' => null]);
    $editor->set('referral_promo', ['text' => "ጓደኞችዎን ይጋብዙ: {referral_link}", 'enabled' => true]);

    app(\App\Services\Bot\WelcomeService::class)->sendWelcome($user);
    $sent = fakeTelegram()->sentTo($user->tg_chat_id);

    expect($sent)->toHaveCount(2)
        ->and($sent[0]['params']['text'])->toContain('Kidus')
        ->and($sent[0]['params']['text'])->toContain('እንኳን')
        ->and($sent[1]['params']['text'])->toContain('https://t.me/LiyubetTestBot?start=ref_'.$user->id);
});

it('sends independent image attachments with each configured message', function () {
    Storage::fake(config('filesystems.default'));
    $user = User::factory()->create();
    $file = MediaFile::factory()->create([
        'path' => 'media/bot-messages/test-photo.jpg',
        'kind' => \App\Enums\MediaKind::Photo,
        'mime' => 'image/jpeg',
    ]);
    Storage::disk(config('filesystems.default'))->put($file->path, 'test-image-bytes');

    app(BotMessageService::class)->set('wallet_balance', [
        'text' => "Balance: {balance}",
        'media_file_id' => $file->id,
    ]);

    $response = app(BotMessageService::class)->send($user, 'wallet_balance', ['balance' => '20.00']);

    expect($response->successful())->toBeTrue()
        ->and(fakeTelegram()->lastSentTo($user->tg_chat_id)['method'])->toBe('sendPhoto')
        ->and(fakeTelegram()->lastSentTo($user->tg_chat_id)['params']['caption'])->toBe('Balance: 20.00');
});

it('splits long image captions so full text is never silently truncated', function () {
    Storage::fake(config('filesystems.default'));
    $user = User::factory()->create();
    $file = MediaFile::factory()->create([
        'path' => 'media/bot-messages/long-photo.jpg',
        'kind' => \App\Enums\MediaKind::Photo,
        'mime' => 'image/jpeg',
    ]);
    Storage::disk(config('filesystems.default'))->put($file->path, 'test-image-bytes');
    $copy = str_repeat('a', 1200);
    app(BotMessageService::class)->set('support_prompt', ['text' => $copy, 'media_file_id' => $file->id]);

    $response = app(BotMessageService::class)->send($user, 'support_prompt');
    $sent = fakeTelegram()->sentTo($user->tg_chat_id);

    expect($response->successful())->toBeTrue()
        ->and($sent)->toHaveCount(2)
        ->and($sent[0]['method'])->toBe('sendPhoto')
        ->and($sent[1]['method'])->toBe('sendMessage')
        ->and($sent[1]['params']['text'])->toBe($copy);
});

it('keeps edits to different bot message keys isolated', function () {
    $service = app(BotMessageService::class);
    $original = $service->content('referral_link')['text'];
    $service->set('join_gate', ['text' => 'Please join the official channel.']);
    $service->set('wallet_balance', ['text' => 'Current balance is {balance} ETB.']);

    expect($service->content('join_gate')['text'])->toBe('Please join the official channel.')
        ->and($service->content('wallet_balance')['text'])->toBe('Current balance is {balance} ETB.')
        ->and($service->content('referral_link')['text'])->toBe($original);
});

it('exposes all individual fixed messages in the Owner backoffice', function () {
    $admin = Admin::factory()->owner()->create();
    $this->actingAs($admin)->get('/admin/bot-messages')->assertOk();

    expect(BotMessageCatalog::get('welcome'))->not->toBeNull()
        ->and(BotMessageCatalog::get('referral_promo'))->not->toBeNull()
        ->and(BotMessageCatalog::get('withdrawal_issued'))->not->toBeNull()
        ->and(BotMessageCatalog::get('history_entry'))->not->toBeNull();
});

it('prevents anonymous access to the bot message editor', function () {
    $this->get('/admin/bot-messages')->assertRedirect();
});
