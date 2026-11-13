<?php

declare(strict_types=1);

namespace App\Telegram\Handlers;

use App\Enums\BotLanguage;
use App\Models\User;
use App\Services\Bot\BotMessageService;
use App\Services\Bot\MenuRenderer;
use App\Services\Bot\WelcomeService;
use App\Services\Rewards\RewardService;
use App\Services\SettingsService;
use App\Services\Telegram\TelegramClient;
use App\Services\Telegram\TelegramMembershipService;

final class RewardsBotHandler
{
    public function __construct(
        private readonly RewardService $rewards,
        private readonly TelegramMembershipService $membership,
        private readonly TelegramClient $client,
        private readonly BotMessageService $messages,
        private readonly MenuRenderer $menus,
        private readonly WelcomeService $welcome,
        private readonly SettingsService $settings,
    ) {}

    public function handle(User $user, string $callbackId, string $data): void
    {
        if (! preg_match('/^rewards:(home|sport|casino|support|referral|balance|history|withdraw|referrals|redeem:(50|100|500))$/D', $data)) {
            $this->client->answerCallbackQuery($callbackId);
            return;
        }

        $requiresFreshCheck = str_starts_with($data, 'rewards:redeem:');
        if (! $this->membership->isMember($user, forceRefresh: $requiresFreshCheck, allowStaleOnError: ! $requiresFreshCheck)) {
            $this->client->answerCallbackQuery($callbackId, 'Join the channel first.', true);
            $this->welcome->sendJoinGate($user);
            return;
        }

        $this->rewards->qualify($user);
        $this->client->answerCallbackQuery($callbackId);
        $this->showAction($user, substr($data, strlen('rewards:')), $callbackId);
    }

    public function handleText(User $user, string $action): void
    {
        if (! in_array($action, ['home', 'sport', 'casino', 'support', 'referral', 'balance', 'history', 'withdraw'], true)) {
            return;
        }
        if (! $this->membership->isMember($user)) {
            $this->welcome->sendJoinGate($user);
            return;
        }
        $this->rewards->qualify($user);
        $this->showAction($user, $action, null);
    }

    private function showAction(User $user, string $action, ?string $callbackId): void
    {
        if ($action === 'home') {
            $this->messages->send($user, 'main_menu', markup: $this->menus->rootKeyboard(BotLanguage::En));
        } elseif (in_array($action, ['sport', 'casino', 'support'], true)) {
            $this->external($user, $action);
        } elseif ($action === 'referral') {
            $this->overview($user);
        } elseif ($action === 'balance') {
            $this->balance($user);
        } elseif ($action === 'referrals') {
            $this->referrals($user);
        } elseif ($action === 'history') {
            $this->history($user);
        } elseif ($action === 'withdraw') {
            $this->withdrawalOptions($user);
        } elseif (str_starts_with($action, 'redeem:') && $callbackId !== null) {
            $this->redeem($user, (int) substr($action, 7), $callbackId);
        }
    }

    private function referralKeyboard(): array
    {
        return ['inline_keyboard' => [
            [['text' => '🔗 My Referral Link', 'callback_data' => 'rewards:referrals']],
            [
                ['text' => '💰 My Balance', 'callback_data' => 'rewards:balance'],
                ['text' => '🎟 Redeem Rewards', 'callback_data' => 'rewards:withdraw'],
            ],
            [['text' => '📜 Withdrawal History', 'callback_data' => 'rewards:history']],
            [['text' => '⬅️ Main Menu', 'callback_data' => 'rewards:home']],
        ]];
    }

    private function overview(User $user): void
    {
        if (! $this->rewards->enabled()) {
            $this->messages->send($user, 'referral_disabled');
            return;
        }

        $s = $this->rewards->stats($user);
        $this->messages->send($user, 'referral_overview', [
            'balance' => number_format($s['balance_cents']/100, 2),
            'earned' => number_format($s['earned_cents']/100, 2),
            'referrals' => $s['referrals'],
            'qualified' => $s['qualified'],
            'withdrawals' => $s['withdrawals'],
        ], $this->referralKeyboard());
    }

    private function balance(User $user): void
    {
        $s = $this->rewards->stats($user);
        $this->messages->send($user, 'wallet_balance', [
            'balance' => number_format($s['balance_cents']/100, 2),
            'earned' => number_format($s['earned_cents']/100, 2),
            'redeemed' => number_format($s['redeemed_cents']/100, 2),
            'status_note' => $s['suspended'] ? "\n\nAccount under review. Please contact support." : '',
        ], $this->referralKeyboard());
    }

    private function referrals(User $user): void
    {
        if (! $this->rewards->enabled()) {
            $this->messages->send($user, 'referral_link_disabled');
            return;
        }
        $s = $this->rewards->stats($user);
        $this->messages->send($user, 'referral_link', [
            'referrals' => $s['referrals'],
            'qualified' => $s['qualified'],
        ], $this->referralKeyboard());
    }

    private function withdrawalOptions(User $user): void
    {
        if (! $this->rewards->withdrawalsEnabled()) {
            $this->messages->send($user, 'withdrawal_disabled');
            return;
        }

        $s = $this->rewards->stats($user);
        $rows = [];
        foreach ($this->rewards->denominations() as $amount) {
            if (in_array($amount, [50, 100, 500], true)) {
                $rows[] = [['text' => "Redeem {$amount} ETB", 'callback_data' => 'rewards:redeem:'.$amount]];
            }
        }
        $rows[] = [['text' => '⬅️ Referral Dashboard', 'callback_data' => 'rewards:referral']];

        $this->messages->send($user, 'withdrawal_options', [
            'balance' => number_format($s['balance_cents']/100, 2),
        ], ['inline_keyboard' => $rows]);
    }

    private function redeem(User $user, int $amount, string $callbackId): void
    {
        $outcome = $this->rewards->redeem($user, $amount, 'telegram:'.$callbackId);
        $messageKey = match ($outcome['status']) {
            'issued' => 'withdrawal_issued',
            'no_stock' => 'withdrawal_no_stock',
            'insufficient' => 'withdrawal_insufficient',
            'suspended' => 'withdrawal_suspended',
            'cooldown' => 'withdrawal_cooldown',
            'not_eligible' => 'withdrawal_not_eligible',
            default => 'withdrawal_unavailable',
        };
        $this->messages->send($user, $messageKey, [
            'amount' => $amount,
            'code' => $outcome['code'] ?? '',
        ], $this->referralKeyboard());
    }

    private function history(User $user): void
    {
        $rows = $this->rewards->history($user, 5);
        $this->messages->send($user, 'history', [
            'count' => count($rows),
            'entries' => '',
        ], $rows === [] ? $this->referralKeyboard() : null);

        if ($rows === []) {
            $this->messages->send($user, 'history_empty', markup: $this->referralKeyboard());
            return;
        }

        foreach ($rows as $index => $row) {
            $this->messages->send($user, 'history_entry', [
                'id' => $row['id'],
                'amount' => $row['amount_etb'],
                'status' => $row['status'],
                'code' => $row['code'],
                'date' => $row['created_at'],
            ], $index === count($rows)-1 ? $this->referralKeyboard() : null);
        }
    }

    private function external(User $user, string $section): void
    {
        $label = ucfirst($section);
        $enabled = (bool) $this->settings->get("rewards.menu_{$section}_enabled", true);
        if (! $enabled) {
            $this->messages->send($user, 'external_disabled', ['section' => $label]);
            return;
        }

        $url = (string) $this->settings->get("rewards.{$section}_url", '');
        if ($url !== '' && filter_var($url, FILTER_VALIDATE_URL) && str_starts_with($url, 'https://')) {
            $this->messages->send($user, 'external_open', ['section' => $label], [
                'inline_keyboard' => [
                    [['text' => 'Open '.$label, 'url' => $url]],
                    [['text' => '⬅️ Main Menu', 'callback_data' => 'rewards:home']],
                ],
            ]);
            return;
        }

        if ($section === 'support') {
            $user->update(['support_status' => 'open']);
            $this->messages->send($user, 'support_prompt');
        } else {
            $this->messages->send($user, 'external_unavailable', ['section' => $label]);
        }
    }
}
