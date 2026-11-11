<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\SettingsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RewardsDiagnose extends Command
{
    protected $signature = 'rewards:diagnose';
    protected $description = 'Check rewards database, channel gating and promo inventory (never prints secrets)';

    public function handle(SettingsService $settings): int
    {
        $tables = ['referral_wallets','referral_rewards','promo_codes','reward_withdrawals','reward_ledger'];
        foreach ($tables as $table) {
            $exists = Schema::hasTable($table);
            $this->line(($exists ? '[OK] ' : '[MISSING] ').$table);
            if (! $exists) {
                $this->error('Run php artisan migrate --force before enabling referrals.');
                return self::FAILURE;
            }
        }

        $requireChannel = (bool) $settings->get('rewards.require_channel', true);
        $channelConfigured = filled($settings->get('channel.id')) && filled($settings->get('channel.url'));
        $this->line('Channel join required: '.($requireChannel ? 'yes' : 'no'));
        $this->line('Channel configured: '.($channelConfigured ? 'yes' : 'no'));
        $this->line('Telegram bot username configured: '.(filled(config('telegram.bot_username')) ? 'yes' : 'no'));
        $this->line('Referral rewards: '.((bool) $settings->get('rewards.enabled', true) ? 'enabled' : 'disabled'));
        $this->line('Promo redemptions: '.((bool) $settings->get('rewards.withdrawals_enabled', true) ? 'enabled' : 'disabled'));
        $this->line('Referral eligibility cutoff set: '.(filled($settings->get('rewards.eligible_since')) ? 'yes' : 'no'));

        foreach ([50, 100, 500] as $amount) {
            $this->line("Available {$amount} ETB codes: ".
                DB::table('promo_codes')->where('denomination_cents', $amount * 100)->where('status', 'available')->count());
        }

        if ($requireChannel && ! $channelConfigured) {
            $this->warn('Set channel ID and URL in Admin > Settings before trying /start.');
        }
        if (! filled($settings->get('rewards.eligible_since'))) {
            $this->error('Referral eligibility cutoff missing. Run migrations.');
            return self::FAILURE;
        }
        return self::SUCCESS;
    }
}
