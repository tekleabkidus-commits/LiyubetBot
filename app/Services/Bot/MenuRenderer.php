<?php

declare(strict_types=1);

namespace App\Services\Bot;

use App\Enums\BotLanguage;
use App\Enums\MenuActionType;
use App\Models\MenuItem;
use App\Services\SettingsService;
use Illuminate\Database\Eloquent\Collection;

/**
 * Renders the menu tree as Telegram inline keyboards in the user's language
 * (spec §5.1). Back button auto-added on submenus; callback protocol per §9.
 */
final class MenuRenderer
{
    public const ROOT = 0;

    public function __construct(private readonly BotLocaleResolver $locale) {}

    /** Keyboard for the main (root) menu, or null when no active items exist. */
    public function rootKeyboard(BotLanguage $lang): ?array
    {
        $settings = app(SettingsService::class);
        $first = [];
        $second = [];
        foreach (['sport' => '⚽ Sport', 'casino' => '🎰 Casino'] as $name => $label) {
            if ((bool) $settings->get("rewards.menu_{$name}_enabled", true)) {
                $first[] = ['text' => (string) $settings->get("rewards.menu_{$name}_label", $label), 'callback_data' => 'rewards:'.$name];
            }
        }
        if ((bool) $settings->get('rewards.menu_referral_enabled', true) && (bool) $settings->get('rewards.enabled', true) && (bool) ($settings->get('features', [])['referrals'] ?? true)) {
            $second[] = ['text' => (string) $settings->get('rewards.menu_referral_label', '🎁 Referral'), 'callback_data' => 'rewards:referral'];
        }
        if ((bool) $settings->get('rewards.menu_support_enabled', true)) {
            $second[] = ['text' => (string) $settings->get('rewards.menu_support_label', '💬 Support'), 'callback_data' => 'rewards:support'];
        }
        $rows = array_values(array_filter([$first, $second]));
        // Preserve access to the legacy Bot Content menu when explicitly enabled.
        if ((bool) $settings->get('rewards.extra_menu_enabled', false)) {
            $extra = $this->activeChildren(null);
            if ($extra->isNotEmpty()) {
                $rows = array_merge($rows, $this->keyboard($extra, $lang, backTo: null)['inline_keyboard']);
            }
        }
        return ['inline_keyboard' => $rows];
    }

    /**
     * Persistent reply keyboard displayed adjacent to the message composer.
     * Reply keyboard buttons submit text messages (unlike inline callbacks).
     * Always match these labels in IncomingMessageHandler.
     */
    public function replyKeyboard(BotLanguage $lang): array
    {
        $settings = app(SettingsService::class);
        $rows = [];

        $top = [];
        foreach (['sport' => '⚽ Sport', 'casino' => '🎰 Casino'] as $action => $default) {
            if ((bool) $settings->get("rewards.menu_{$action}_enabled", true)) {
                $top[] = ['text' => (string) $settings->get("rewards.menu_{$action}_label", $default)];
            }
        }
        if ($top !== []) {
            $rows[] = $top;
        }

        $second = [];
        $features = (array) $settings->get('features', []);
        if ((bool) $settings->get('rewards.menu_referral_enabled', true) &&
            (bool) $settings->get('rewards.enabled', true) && (bool) ($features['referrals'] ?? true)) {
            $second[] = ['text' => (string) $settings->get('rewards.menu_referral_label', '🎁 Referral')];
        }
        if ((bool) $settings->get('rewards.menu_support_enabled', true)) {
            $second[] = ['text' => (string) $settings->get('rewards.menu_support_label', '💬 Support')];
        }
        if ($second !== []) {
            $rows[] = $second;
        }

        $rows[] = [['text' => '🏠 Start'], ['text' => '💰 Balance']];
        $rows[] = [['text' => '🎟 Withdraw'], ['text' => '📜 History']];

        return [
            'keyboard' => $rows,
            'resize_keyboard' => true,
            'is_persistent' => true,
            'one_time_keyboard' => false,
            'input_field_placeholder' => 'Choose Sport, Casino, Referral or Support…',
        ];
    }

    /**
     * Determine which action a reply keyboard text or slash command selects.
     * Return null for arbitrary customer messages so keyword/support handling
     * continues working normally.
     */
    public function actionFromText(string $text): ?string
    {
        $text = trim($text);
        if ($text === '') {
            return null;
        }

        if (preg_match('~^/([a-z]+)(?:@([A-Za-z0-9_]+))?$~iD', $text, $command)) {
            $target = $command[2] ?? null;
            $username = ltrim((string) config('telegram.bot_username'), '@');
            if ($target !== null && $username !== '' && strcasecmp($target, $username) !== 0) {
                return null;
            }
            return match (strtolower($command[1])) {
                'start', 'menu', 'help' => 'home',
                'sport', 'casino', 'referral', 'support', 'balance' => strtolower($command[1]),
                'withdraw' => 'withdraw',
                'history' => 'history',
                default => null,
            };
        }

        $normalized = mb_strtolower($text);
        $actions = [
            '🏠 start' => 'home', 'start' => 'home', '🏠 menu' => 'home',
            '💰 balance' => 'balance', 'balance' => 'balance',
            '🎟 withdraw' => 'withdraw', 'withdraw' => 'withdraw',
            '📜 history' => 'history', 'history' => 'history',
        ];
        foreach (['sport' => '⚽ Sport', 'casino' => '🎰 Casino',
            'referral' => '🎁 Referral', 'support' => '💬 Support'] as $action => $default) {
            $configured = (string) app(SettingsService::class)->get("rewards.menu_{$action}_label", $default);
            $actions[mb_strtolower($configured)] = $action;
            $actions[mb_strtolower($default)] = $action;
        }
        return $actions[$normalized] ?? null;
    }

    /** Keyboard for a submenu's children, with a Back button appended. */
    public function submenuKeyboard(MenuItem $item, BotLanguage $lang): array
    {
        $items = $this->activeChildren($item->id);

        // Back re-renders the menu that contains $item.
        return $this->keyboard($items, $lang, backTo: $item->parent_id ?? self::ROOT);
    }

    /** @param  Collection<int, MenuItem>  $items */
    private function keyboard(Collection $items, BotLanguage $lang, ?int $backTo): array
    {
        $features = app(SettingsService::class)->get('features', []);
        $items = $items->filter(fn ($item) => ($item->action_type !== MenuActionType::Webapp || ($features['mini_app'] ?? true)) && ($item->action_type !== MenuActionType::Invite || ($features['referrals'] ?? true)));
        $items = $items->filter(fn ($item) => $item->action_type !== MenuActionType::Webapp || str_starts_with((string) ($item->url ?: app(SettingsService::class)->get('webapp.url')), 'https://'));
        $rows = $items
            ->map(fn (MenuItem $item) => [$this->button($item, $lang)])
            ->values()
            ->all();

        if ($backTo !== null) {
            $rows[] = [[
                'text' => $this->locale->uiText('back', $lang),
                'callback_data' => 'menu:'.$backTo,
            ]];
        }

        return ['inline_keyboard' => $rows];
    }

    private function button(MenuItem $item, BotLanguage $lang): array
    {
        $translation = $this->locale->pickTranslation($item->translations, $lang);
        $label = $translation?->label ?? '—';

        return match ($item->action_type) {
            MenuActionType::Url => ['text' => $label, 'url' => (string) $item->url],
            MenuActionType::Webapp => ['text' => $label, 'web_app' => ['url' => (string) ($item->url ?: app(SettingsService::class)->get('webapp.url'))]],
            MenuActionType::Invite => ['text' => $label, 'callback_data' => 'invite:show'],
            MenuActionType::Reply,
            MenuActionType::Submenu => ['text' => $label, 'callback_data' => 'menu:'.$item->id],
        };
    }

    /** @return Collection<int, MenuItem> */
    private function activeChildren(?int $parentId): Collection
    {
        return MenuItem::query()
            ->where('is_active', true)
            ->when(
                $parentId === null,
                fn ($q) => $q->whereNull('parent_id'),
                fn ($q) => $q->where('parent_id', $parentId),
            )
            ->orderBy('position')
            ->orderBy('id')
            ->with('translations')
            ->get();
    }
}
