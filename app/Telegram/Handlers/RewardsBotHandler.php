<?php

declare(strict_types=1);

namespace App\Telegram\Handlers;

use App\Models\User;
use App\Services\Bot\BotMessageSender;
use App\Services\Bot\MenuRenderer;
use App\Services\Bot\WelcomeService;
use App\Services\Rewards\RewardService;
use App\Services\SettingsService;
use App\Services\Telegram\TelegramClient;
use App\Services\Telegram\TelegramMembershipService;
use Illuminate\Support\Facades\DB;

/** Telegram UI for the referral wallet and single-use promotion redemptions. */
final class RewardsBotHandler
{
    public function __construct(
        private readonly RewardService $rewards,
        private readonly TelegramMembershipService $membership,
        private readonly TelegramClient $client,
        private readonly BotMessageSender $sender,
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

        $action = substr($data, strlen('rewards:'));
        if ($action === 'home') {
            $this->sender->sendToUser($user, 'Welcome to Liyubet. Choose a service:', replyMarkup: $this->menus->rootKeyboard(\App\Enums\BotLanguage::En));
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
        } elseif (str_starts_with($action, 'redeem:')) {
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
            $this->sender->sendToUser($user, 'Referrals are currently unavailable.');
            return;
        }
        $s = $this->rewards->stats($user);
        $this->sender->sendToUser($user,
            "🎁 Liyubet Referral Rewards\n\nEarn ".(int) $this->settings->get('rewards.amount_etb', 2)." ETB for every eligible new referral.\n\n".
            'Available: '.number_format($s['balance_cents'] / 100, 2)." ETB\n".
            "Invited users: {$s['referrals']}\nQualified referrals: {$s['qualified']}\n".
            'Lifetime earned: '.number_format($s['earned_cents'] / 100, 2)." ETB\n".
            "Withdrawals: {$s['withdrawals']}",
            replyMarkup: $this->referralKeyboard()
        );
    }

    private function balance(User $user): void
    {
        $s = $this->rewards->stats($user);
        $this->sender->sendToUser($user,
            "💰 Referral Wallet\n\nAvailable: ".number_format($s['balance_cents'] / 100, 2).
            " ETB\nEarned: ".number_format($s['earned_cents'] / 100, 2).
            " ETB\nRedeemed: ".number_format($s['redeemed_cents'] / 100, 2).
            " ETB".($s['suspended'] ? "\n\nAccount under review. Please contact support." : ''),
            replyMarkup: $this->referralKeyboard()
        );
    }

    private function referrals(User $user): void
    {
        if (! $this->rewards->enabled()) {
            $this->sender->sendToUser($user, 'Referrals are temporarily disabled.');
            return;
        }
        $s = $this->rewards->stats($user);
        $username = (string) config('telegram.bot_username');
        $link = $username !== '' ? 'https://t.me/'.ltrim($username, '@').'?start=ref_'.$user->id : 'Bot username is not configured.';
        $this->sender->sendToUser($user,
            "🔗 Your personal referral link\n{$link}\n\nShare this link with friends. Only new users who verify channel membership count.\n".
            "Invited: {$s['referrals']} | Qualified: {$s['qualified']}\nReward per eligible referral: ".
            (int) $this->settings->get('rewards.amount_etb', 2).' ETB.',
            replyMarkup: $this->referralKeyboard()
        );
    }

    private function withdrawalOptions(User $user): void
    {
        if (! $this->rewards->withdrawalsEnabled()) {
            $this->sender->sendToUser($user, 'Reward redemptions are temporarily unavailable.');
            return;
        }
        $s = $this->rewards->stats($user);
        $rows = [];
        foreach ($this->rewards->denominations() as $amount) {
            if (! in_array($amount, [50, 100, 500], true)) {
                continue;
            }
            $rows[] = [['text' => "Redeem {$amount} ETB", 'callback_data' => 'rewards:redeem:'.$amount]];
        }
        $rows[] = [['text' => '⬅️ Referral Dashboard', 'callback_data' => 'rewards:referral']];
        $this->sender->sendToUser($user,
            "🎟 Redeem Referral Rewards\n\nAvailable balance: ".number_format($s['balance_cents'] / 100, 2).
            " ETB\nChoose an amount below. An available single-use promo code will be issued. ".
            "If codes are out of stock, your balance will NOT be deducted.",
            replyMarkup: ['inline_keyboard' => $rows]
        );
    }

    private function redeem(User $user, int $amount, string $callbackId): void
    {
        $outcome = $this->rewards->redeem($user, $amount, 'telegram:'.$callbackId);
        $brand = (string) $this->settings->get('rewards.redemption_brand', 'Kemerbet');
        $message = match ($outcome['status']) {
            'issued' => "✅ Your {$amount} ETB promo code:\n".$outcome['code']."\n\nUse this code in your {$brand} account. Keep it private. You can recover it from Withdrawal History.",
            'no_stock' => "⏳ {$amount} ETB promo codes are temporarily unavailable. Please wait and try again. Your balance is unchanged.",
            'insufficient' => "Your referral balance is below {$amount} ETB. Invite more friends to earn rewards.",
            'suspended' => 'Your rewards are under review. Contact support.',
            'cooldown' => 'Please check Withdrawal History before requesting another promo code.',
            'not_eligible' => 'Verify your channel membership before redeeming rewards.',
            default => 'This redemption option is currently unavailable.',
        };
        $this->sender->sendToUser($user, $message, replyMarkup: $this->referralKeyboard());
    }

    private function history(User $user): void
    {
        $history = $this->rewards->history($user, 5);
        $lines = ["📜 Your last ".count($history)." reward redemptions"];
        foreach ($history as $withdrawal) {
            $lines[] = "\n#{$withdrawal['id']} — {$withdrawal['amount_etb']} ETB ({$withdrawal['status']})\n".
                "Code: {$withdrawal['code']}\nDate: {$withdrawal['created_at']}";
        }
        if (! $history) {
            $lines[] = '\nNo redemptions yet.';
        }
        $this->sender->sendToUser($user, implode("\n", $lines), replyMarkup: $this->referralKeyboard());
    }

    private function external(User $user, string $section): void
    {
        $enabled = (bool) $this->settings->get("rewards.menu_{$section}_enabled", true);
        if (! $enabled) {
            $this->sender->sendToUser($user, ucfirst($section).' is currently unavailable.');
            return;
        }
        $url = (string) $this->settings->get("rewards.{$section}_url", '');
        if ($url !== '' && filter_var($url, FILTER_VALIDATE_URL) && str_starts_with($url, 'https://')) {
            $this->sender->sendToUser($user, 'Open '.ucfirst($section).' using the button below:',
                replyMarkup: ['inline_keyboard' => [
                    [['text' => 'Open '.ucfirst($section), 'url' => $url]],
                    [['text' => '⬅️ Main Menu', 'callback_data' => 'rewards:home']],
                ]]);
            return;
        }

        if ($section === 'support') {
            $user->update(['support_status' => 'open']);
            $this->sender->sendToUser($user, "💬 Send your question here. Our support team can reply through the admin inbox.");
        } else {
            $this->sender->sendToUser($user, ucfirst($section).' link is not configured yet.');
        }
    }
}
