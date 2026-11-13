<?php

declare(strict_types=1);

namespace App\Services\Bot;

use App\Models\User;

final class PreferencesHandler
{
    public function handle(User $user, string $text): bool
    {
        $command = strtolower(trim($text));
        $key = null;

        if (in_array($command, ['/stop', '/unsubscribe'], true)) {
            $user->update(['marketing_subscribed' => false]);
            $key = 'preferences_unsubscribed';
        } elseif ($command === '/subscribe') {
            $user->update(['marketing_subscribed' => true]);
            $key = 'preferences_subscribed';
        } elseif (str_starts_with($command, '/language')) {
            $user->update(['preferred_language' => 'en']);
            $key = 'language_notice';
        } elseif (preg_match('/^\/topics (general|matches|offers|news)(,(general|matches|offers|news))*$/', $command)) {
            $user->update(['topics' => array_values(array_unique(explode(',', substr($command, 8))))]);
            $key = 'preferences_saved';
        } elseif (preg_match('/^\/frequency ([1-9])$/', $command, $match)) {
            $user->update(['daily_message_limit' => (int) $match[1]]);
            $key = 'preferences_saved';
        } elseif ($command === '/preferences') {
            $key = 'preferences_help';
        }

        if ($key === null) {
            return false;
        }
        app(BotMessageService::class)->send($user, $key);
        return true;
    }
}
