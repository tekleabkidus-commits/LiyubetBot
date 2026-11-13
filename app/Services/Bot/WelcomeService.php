<?php

declare(strict_types=1);

namespace App\Services\Bot;

use App\Models\User;
use App\Services\SettingsService;
use App\Services\Telegram\TelegramResponse;

/** Onboarding messages are editable in Bot Content > Bot Messages. */
final class WelcomeService
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly BotLocaleResolver $locale,
        private readonly MenuRenderer $menus,
        private readonly BotMessageService $messages,
    ) {}

    public function sendWelcome(User $user): TelegramResponse
    {
        $lang = $this->locale->resolve($user);
        $response = $this->messages->send($user, 'welcome', markup: $this->menus->replyKeyboard($lang));

        // This is an independently managed second message, OFF by default.
        if ($response->successful() && $this->messages->enabled('referral_promo')
            && (bool) ($this->settings->get('features', [])['referrals'] ?? true)
            && (bool) $this->settings->get('rewards.enabled', true)) {
            $this->messages->send($user, 'referral_promo');
        }

        return $response;
    }

    public function sendJoinGate(User $user): TelegramResponse
    {
        $lang = $this->locale->resolve($user);
        $rows = [];
        $channelUrl = $this->settings->get('channel.url');

        if (is_string($channelUrl) && $channelUrl !== '') {
            $rows[] = [['text' => $this->locale->uiText('join_button', $lang), 'url' => $channelUrl]];
        }

        $rows[] = [['text' => $this->locale->uiText('joined_check_button', $lang), 'callback_data' => 'join:check']];

        return $this->messages->send($user, 'join_gate', markup: ['inline_keyboard' => $rows]);
    }
}
