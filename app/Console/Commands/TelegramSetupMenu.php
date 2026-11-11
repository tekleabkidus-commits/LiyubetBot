<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Telegram\TelegramClient;
use Illuminate\Console\Command;

/**
 * Installs the native Telegram "Menu" button next to the typing field,
 * and the slash-command list shown when the user taps it.
 * The persistent reply keyboard itself is delivered by /start.
 */
final class TelegramSetupMenu extends Command
{
    protected $signature = 'telegram:setup-menu';
    protected $description = 'Configure the native Telegram command menu next to the chat input field';

    public function handle(TelegramClient $client): int
    {
        if (! filled(config('telegram.bot_token'))) {
            $this->error('TELEGRAM_BOT_TOKEN is not configured.');
            return self::FAILURE;
        }

        $commands = [
            ['command' => 'start', 'description' => 'Start or restart LiyubetBot'],
            ['command' => 'menu', 'description' => 'Show the main Liyubet menu'],
            ['command' => 'sport', 'description' => 'Open Sport'],
            ['command' => 'casino', 'description' => 'Open Casino'],
            ['command' => 'referral', 'description' => 'Referral link and rewards'],
            ['command' => 'balance', 'description' => 'Check your referral wallet'],
            ['command' => 'withdraw', 'description' => 'Redeem referral rewards'],
            ['command' => 'history', 'description' => 'View reward withdrawals'],
            ['command' => 'support', 'description' => 'Contact support'],
            ['command' => 'help', 'description' => 'How to use LiyubetBot'],
        ];

        $result = $client->setMyCommands($commands);
        if (! $result->successful()) {
            $this->error('Telegram rejected the command list: '.($result->description ?? 'unknown error'));
            return self::FAILURE;
        }

        $button = $client->setChatMenuButton(['type' => 'commands']);
        if (! $button->successful()) {
            $this->error('Commands installed, but Telegram rejected the Menu button: '.($button->description ?? 'unknown error'));
            return self::FAILURE;
        }

        $this->info('Telegram Menu button installed successfully.');
        $this->line('Commands: '.implode(', ', array_map(fn (array $row): string => '/'.$row['command'], $commands)));
        $this->line('Open the bot and send /start to show the persistent reply keyboard.');
        return self::SUCCESS;
    }
}
