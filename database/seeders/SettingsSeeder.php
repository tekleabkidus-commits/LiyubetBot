<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

/**
 * Default runtime settings (spec §5.9). Only creates missing keys so re-seeding
 * never clobbers admin-edited values. Secrets never live here (spec §4.7).
 */
class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            'channel.id' => null,
            'channel.url' => null,
            'bot.default_language' => 'en',
            'telegram.send_rate' => (int) config('telegram.send_rate'),
            'broadcast.test_recipient_chat_ids' => [],
            'webapp.url' => null,
            'rewards.enabled' => true,
            'rewards.withdrawals_enabled' => true,
            'rewards.require_channel' => true,
            'rewards.amount_etb' => 2,
            'rewards.denominations' => [50, 100, 500],
            'rewards.redemption_brand' => 'Kemerbet',
            'rewards.sport_url' => null,
            'rewards.casino_url' => null,
            'rewards.support_url' => null,
            'rewards.menu_sport_enabled' => true,
            'rewards.menu_casino_enabled' => true,
            'rewards.menu_referral_enabled' => true,
            'rewards.menu_support_enabled' => true,
            'rewards.menu_sport_label' => '⚽ Sport',
            'rewards.menu_casino_label' => '🎰 Casino',
            'rewards.menu_referral_label' => '🎁 Referral',
            'rewards.menu_support_label' => '💬 Support',
            'welcome.message' => [
                'en' => "Welcome to Liyubet, {first_name}! ⚽\nUse the menu below to explore matches, promos and more.",
            ],
            'welcome.media_file_id' => null,
            'features' => [
                'referrals' => true,
                'polls' => true,
                'mini_app' => true,
            ],
        ];

        foreach ($defaults as $key => $value) {
            Setting::query()->firstOrCreate(['key' => $key], ['value' => $value]);
        }
    }
}
