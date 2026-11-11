<?php

declare(strict_types=1);

namespace App\Telegram\Handlers;

use App\Models\KeywordReply;
use App\Models\User;
use App\Services\Bot\BotLocaleResolver;
use App\Services\Bot\BotMessageSender;
use App\Services\Bot\EmbeddedButtonsRenderer;
use App\Services\Bot\InboundMessageRecorder;
use App\Services\Bot\KeywordMatcher;
use App\Services\Bot\MenuRenderer;
use App\Services\Bot\PreferencesHandler;
use App\Services\Bot\UserService;
use App\Services\Bot\WelcomeService;
use App\Services\Rewards\RewardService;
use App\Services\Telegram\TelegramMembershipService;

/**
 * Non-command inbound messages: capture, activity + blocked recovery, join
 * gate, then specific keyword rules, followed by an optional any-message rule.
 */
final class IncomingMessageHandler
{
    public function __construct(
        private readonly UserService $users,
        private readonly InboundMessageRecorder $inbound,
        private readonly TelegramMembershipService $membership,
        private readonly WelcomeService $welcome,
        private readonly KeywordMatcher $keywords,
        private readonly BotLocaleResolver $locale,
        private readonly BotMessageSender $sender,
        private readonly EmbeddedButtonsRenderer $embeddedButtons,
        private readonly RewardService $rewards,
        private readonly MenuRenderer $menus,
        private readonly RewardsBotHandler $rewardsBot,
    ) {}

    public function handle(array $message): void
    {
        $from = $message['from'] ?? null;

        if (! is_array($from) || ($from['is_bot'] ?? false) || (($message['chat']['type'] ?? 'private') !== 'private')) {
            return;
        }

        [$user] = $this->users->upsertFromTelegram($from);
        $this->users->recordActivity($user);
        $this->inbound->record($user, $message);

        if (! $this->membership->isMember($user)) {
            $this->welcome->sendJoinGate($user);

            return;
        }

        $this->rewards->qualify($user);
        if (app(PreferencesHandler::class)->handle($user, (string) ($message['text'] ?? ''))) {
            return;
        }
        $textValue = trim((string) ($message['text'] ?? ''));
        if (($action = $this->menus->actionFromText($textValue)) !== null) {
            if ($action === 'home') {
                $this->welcome->sendWelcome($user);
            } else {
                $this->rewardsBot->handleText($user, $action);
            }
            return;
        }

        // Photo, video, and other messages without text can use the catch-all.
        $text = $message['text'] ?? $message['caption'] ?? '';
        $rule = $this->keywords->match(is_string($text) ? $text : '');

        if ($rule !== null) {
            $this->sendKeywordReply($user, $rule);
        } else {
            $user->update(['support_status' => 'open']);
        }
    }

    private function sendKeywordReply(User $user, KeywordReply $rule): void
    {
        $lang = $this->locale->resolve($user);
        $translation = $this->locale->pickTranslation($rule->translations, $lang);

        if ($translation === null) {
            return;
        }

        $this->sender->sendToUser(
            $user,
            $translation->reply_text,
            $rule->mediaFile,
            $this->embeddedButtons->render($rule->buttons, $lang),
        );
    }
}
