<?php

declare(strict_types=1);

namespace App\Services\Bot;

use App\Models\MediaFile;
use App\Models\User;
use App\Services\SettingsService;
use App\Services\Telegram\TelegramResponse;
use Illuminate\Support\Facades\Log;

/** Central source for runtime-editable bot messages, media, and placeholders. */
final class BotMessageService
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly TokenRenderer $tokens,
        private readonly BotMessageSender $sender,
    ) {}

    public function content(string $key): array
    {
        $spec = BotMessageCatalog::get($key);
        if ($spec === null) {
            throw new \InvalidArgumentException("Unknown bot message: {$key}");
        }

        $saved = (array) $this->settings->get('bot.messages.'.$key, []);
        $text = (string) ($saved['text'] ?? $spec['text']);
        $mediaId = $saved['media_file_id'] ?? null;

        // Keep the previously configured welcome text and image on migration.
        // Existing sites are not silently rewritten by this feature.
        if ($key === 'welcome' && ! array_key_exists('text', $saved)) {
            $legacy = (array) $this->settings->get('welcome.message', []);
            $existing = trim((string) ($legacy['en'] ?? ''));
            if ($existing !== '') {
                $text = $existing."\n\n🎁 Your referral link: {referral_link}\nTap Referral to check your earnings and withdraw rewards.";
            }
        }
        if ($key === 'welcome' && ! array_key_exists('media_file_id', $saved)) {
            $mediaId = $this->settings->get('welcome.media_file_id');
        }

        return [
            'text' => $text,
            'media_file_id' => $mediaId !== null ? (int) $mediaId : null,
            'enabled' => (bool) ($saved['enabled'] ?? ! ($spec['optional'] ?? false)),
        ];
    }

    public function variables(User $user, array $extra = []): array
    {
        $username = ltrim((string) config('telegram.bot_username'), '@');
        $referralLink = $username !== ''
            ? 'https://t.me/'.$username.'?start=ref_'.$user->id
            : 'Referral link is unavailable.';

        return array_merge([
            'first_name' => (string) ($user->first_name ?: 'Friend'),
            'referral_link' => $referralLink,
            'reward_amount' => (string) (int) $this->settings->get('rewards.amount_etb', 2),
            'brand' => (string) $this->settings->get('rewards.redemption_brand', 'Kemerbet'),
        ], array_map(fn ($v) => is_scalar($v) ? (string) $v : '', $extra));
    }

    public function render(string $key, User $user, array $tokens = []): string
    {
        $content = $this->content($key);
        return $this->tokens->render($content['text'], $this->variables($user, $tokens));
    }

    public function enabled(string $key): bool
    {
        return $this->content($key)['enabled'];
    }

    /**
     * When media is attached and copy exceeds Telegram's 1024-character photo
     * caption limit, send the image separately then deliver full text with
     * reply buttons. Never truncate a financial code or referral link.
     */
    public function send(User $user, string $key, array $tokens = [], ?array $markup = null): TelegramResponse
    {
        $content = $this->content($key);
        if (! $content['enabled']) {
            return TelegramResponse::success();
        }

        $body = $this->render($key, $user, $tokens);
        $media = $content['media_file_id'] !== null
            ? MediaFile::query()->find($content['media_file_id']) : null;

        if ($media !== null && mb_strlen($body) > 1024) {
            $imageResponse = $this->sender->sendToUser($user, null, $media);
            if (! $imageResponse->successful()) {
                Log::warning('bot.message.media_failed', ['key' => $key, 'media_id' => $media->id]);
            }
            return $this->sender->sendToUser($user, $body, replyMarkup: $markup);
        }

        if ($media !== null) {
            $result = $this->sender->sendToUser($user, $body, $media, $markup);
            if ($result->successful()) {
                return $result;
            }
            Log::warning('bot.message.media_failed', ['key' => $key, 'media_id' => $media->id]);
        }
        return $this->sender->sendToUser($user, $body, replyMarkup: $markup);
    }

    public function set(string $key, array $fields): void
    {
        if (BotMessageCatalog::get($key) === null) {
            throw new \InvalidArgumentException('Unknown message key.');
        }
        $spec = BotMessageCatalog::get($key);
        $this->settings->set('bot.messages.'.$key, [
            'text' => $fields['text'],
            'media_file_id' => $fields['media_file_id'] ?? null,
            'enabled' => ($spec['optional'] ?? false) ? (bool) ($fields['enabled'] ?? false) : true,
        ]);
    }
}
