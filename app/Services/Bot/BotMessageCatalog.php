<?php

declare(strict_types=1);

namespace App\Services\Bot;

/**
 * All fixed customer-facing message copy. Business-generated values stay in
 * placeholders; operators may edit the wording but not the accounting.
 * Broadcasts, automations and keyword-rule content have their own editors.
 */
final class BotMessageCatalog
{
    /** @return array<string,array{group:string,title:string,description:string,text:string,tokens:array,optional?:bool}> */
    public static function all(): array
    {
        return [
            'welcome' => [
                'group'=>'Onboarding','title'=>'Welcome message','description'=>'Sent after /start or a successful channel join. Main reply keyboard is attached.',
                'text'=>"Welcome to Liyubet, {first_name}! ⚽\nUse the menu below to explore matches, promos and more.\n\n🎁 Your referral link: {referral_link}\nTap Referral to check your earnings and withdraw rewards.",
                'tokens'=>['first_name','referral_link'],
            ],
            'referral_promo' => [
                'group'=>'Onboarding','title'=>'Second referral-promotion message','description'=>'Optional follow-up after the welcome. Disabled by default until approved.',
                'text'=>"🎁 Invite friends to Liyubet!\nYour personal referral link: {referral_link}",
                'tokens'=>['first_name','referral_link','reward_amount'],'optional'=>true,
            ],
            'join_gate' => [
                'group'=>'Onboarding','title'=>'Mandatory join-channel prompt','description'=>'Shown before access when Telegram membership cannot be confirmed.',
                'text'=>"To use the Liyubet bot, please join our channel first. 📣\nTap \"Join channel\", then \"I've joined ✅\".",'tokens'=>[],
            ],
            'main_menu' => [
                'group'=>'Onboarding','title'=>'Main menu heading','description'=>'Sent on the Home / Menu button.',
                'text'=>'Welcome to Liyubet. Choose a service:','tokens'=>['first_name'],
            ],
            'referral_overview' => [
                'group'=>'Referral','title'=>'Referral dashboard',
                'description'=>'Displayed when users tap Referral; values are filled from the wallet.',
                'text'=>"🎁 Liyubet Referral Rewards\n\nEarn {reward_amount} ETB for every eligible new referral.\n\nAvailable: {balance} ETB\nInvited users: {referrals}\nQualified referrals: {qualified}\nLifetime earned: {earned} ETB\nWithdrawals: {withdrawals}",
                'tokens'=>['reward_amount','balance','referrals','qualified','earned','withdrawals'],
            ],
            'referral_link' => [
                'group'=>'Referral','title'=>'Personal referral link','description'=>'Sent when users request their invitation link.',
                'text'=>"🔗 Your personal referral link\n{referral_link}\n\nShare this link with friends. Only new users who verify channel membership count.\nInvited: {referrals} | Qualified: {qualified}\nReward per eligible referral: {reward_amount} ETB.",
                'tokens'=>['referral_link','referrals','qualified','reward_amount'],
            ],
            'wallet_balance' => [
                'group'=>'Referral','title'=>'Wallet balance','description'=>'Shows balance, earnings and redeemed totals.',
                'text'=>"💰 Referral Wallet\n\nAvailable: {balance} ETB\nEarned: {earned} ETB\nRedeemed: {redeemed} ETB{status_note}",
                'tokens'=>['balance','earned','redeemed','status_note'],
            ],
            'referral_disabled' => [
                'group'=>'Referral','title'=>'Referral program unavailable','description'=>'Shown when referrals are switched off.',
                'text'=>'Referrals are currently unavailable.','tokens'=>[],
            ],
            'referral_link_disabled' => [
                'group'=>'Referral','title'=>'Referral link unavailable','description'=>'Shown when a referral link cannot currently be issued.',
                'text'=>'Referrals are temporarily disabled.','tokens'=>[],
            ],
            'withdrawal_options' => [
                'group'=>'Redemptions','title'=>'Choose redemption amount','description'=>'Sent with clickable 50, 100, 500 ETB buttons.',
                'text'=>"🎟 Redeem Referral Rewards\n\nAvailable balance: {balance} ETB\nChoose an amount below. An available single-use promo code will be issued. If codes are out of stock, your balance will NOT be deducted.",
                'tokens'=>['balance'],
            ],
            'withdrawal_disabled' => [
                'group'=>'Redemptions','title'=>'Withdrawals disabled','description'=>'Shown when redemptions are turned off.',
                'text'=>'Reward redemptions are temporarily unavailable.','tokens'=>[],
            ],
            'withdrawal_issued' => [
                'group'=>'Redemptions','title'=>'Successful promo-code withdrawal','description'=>'Contains an issued private code. Never remove {code} or {brand} without understanding the consequences.',
                'text'=>"✅ Your {amount} ETB promo code:\n{code}\n\nUse this code in your {brand} account. Keep it private. You can recover it from Withdrawal History.",
                'tokens'=>['amount','code','brand'],
            ],
            'withdrawal_no_stock' => [
                'group'=>'Redemptions','title'=>'No promo codes in stock','description'=>'Balance stays unchanged.',
                'text'=>"⏳ {amount} ETB promo codes are temporarily unavailable. Please wait and try again. Your balance is unchanged.",
                'tokens'=>['amount'],
            ],
            'withdrawal_insufficient' => [
                'group'=>'Redemptions','title'=>'Insufficient balance','description'=>'Sent if the user lacks credits.',
                'text'=>'Your referral balance is below {amount} ETB. Invite more friends to earn rewards.','tokens'=>['amount'],
            ],
            'withdrawal_suspended' => [
                'group'=>'Redemptions','title'=>'Wallet under review','description'=>'Sent when the wallet is suspended.',
                'text'=>'Your rewards are under review. Contact support.','tokens'=>[],
            ],
            'withdrawal_cooldown' => [
                'group'=>'Redemptions','title'=>'Repeated withdrawal attempt','description'=>'Shown after a rapid repeat request.',
                'text'=>'Please check Withdrawal History before requesting another promo code.','tokens'=>[],
            ],
            'withdrawal_not_eligible' => [
                'group'=>'Redemptions','title'=>'Membership not verified for withdrawal','description'=>'Shown if channel verification is missing.',
                'text'=>'Verify your channel membership before redeeming rewards.','tokens'=>[],
            ],
            'withdrawal_unavailable' => [
                'group'=>'Redemptions','title'=>'Unsupported redemption','description'=>'Fallback for an unavailable redemption option.',
                'text'=>'This redemption option is currently unavailable.','tokens'=>[],
            ],
            'history' => [
                'group'=>'Redemptions','title'=>'Withdrawal history','description'=>'A summary followed by the last five transaction records.',
                'text'=>"📜 Your last {count} reward redemptions",
                'tokens'=>['count'],
            ],
            'history_entry' => [
                'group'=>'Redemptions','title'=>'Individual history entry','description'=>'Repeated for each recent redemption. {code} is the original private code.',
                'text'=>"#{id} — {amount} ETB ({status})\nCode: {code}\nDate: {date}",
                'tokens'=>['id','amount','status','code','date'],
            ],
            'history_empty' => [
                'group'=>'Redemptions','title'=>'No withdrawal history','description'=>'Shown when the user has no redemptions.',
                'text'=>'No redemptions yet.','tokens'=>[],
            ],
            'external_open' => [
                'group'=>'Navigation','title'=>'Open Sport / Casino / Support','description'=>'Sent when a configured HTTPS link exists.',
                'text'=>'Open {section} using the button below:','tokens'=>['section'],
            ],
            'external_unavailable' => [
                'group'=>'Navigation','title'=>'Link not configured','description'=>'Shown when the target URL is missing.',
                'text'=>'{section} link is not configured yet.','tokens'=>['section'],
            ],
            'external_disabled' => [
                'group'=>'Navigation','title'=>'Menu option disabled','description'=>'Shown when an individual menu button is disabled.',
                'text'=>'{section} is currently unavailable.','tokens'=>['section'],
            ],
            'support_prompt' => [
                'group'=>'Navigation','title'=>'Support conversation prompt','description'=>'Invites users to message support.',
                'text'=>'💬 Send your question here. Our support team can reply through the admin inbox.','tokens'=>[],
            ],
            'preferences_subscribed' => [
                'group'=>'Preferences','title'=>'Promotions enabled','description'=>'Reply to /subscribe.',
                'text'=>'You are subscribed. Use /preferences to choose what you receive.','tokens'=>[],
            ],
            'preferences_unsubscribed' => [
                'group'=>'Preferences','title'=>'Promotions paused','description'=>'Reply to /stop or /unsubscribe.',
                'text'=>'You have paused promotional messages. Use /subscribe to receive them again.','tokens'=>[],
            ],
            'preferences_saved' => [
                'group'=>'Preferences','title'=>'Preferences updated','description'=>'Acknowledges topics and frequency changes.',
                'text'=>'Your preferences are saved.','tokens'=>[],
            ],
            'preferences_help' => [
                'group'=>'Preferences','title'=>'Preferences help','description'=>'Reply to /preferences.',
                'text'=>"Your messages, your choice.\n/stop — pause promotions\n/subscribe — receive promotions\nAll bot messages are in English\n/topics general,matches,offers,news\n/frequency 1 — maximum promotions per day",
                'tokens'=>[],
            ],
            'language_notice' => [
                'group'=>'Preferences','title'=>'Language command response','description'=>'Reply to /language.',
                'text'=>'This bot uses English only.','tokens'=>[],
            ],
        ];
    }

    public static function get(string $key): ?array
    {
        return self::all()[$key] ?? null;
    }

    public static function groups(): array
    {
        $result = [];
        foreach (self::all() as $key => $row) {
            $result[$row['group']][$key] = $row;
        }
        return $result;
    }
}
