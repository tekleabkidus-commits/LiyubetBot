<?php

namespace App\Filament\Pages;

use App\Services\AuditLogger;
use App\Services\SettingsService;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class RewardSettings extends Page
{
    protected string $view = 'filament.pages.reward-settings';
    protected static ?string $navigationLabel = 'Reward Settings';
    protected static string|\UnitEnum|null $navigationGroup = 'Rewards';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static ?int $navigationSort = 5;

    public ?array $data = [];

    protected function getViewData(): array
    {
        return ['rewardRate' => (int) app(SettingsService::class)->get('rewards.amount_etb', 2)];
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->isOwner() === true;
    }

    public function mount(): void
    {
        $settings = app(SettingsService::class);
        $input = [
            'enabled' => 'rewards.enabled',
            'withdrawals_enabled' => 'rewards.withdrawals_enabled',
            'require_channel' => 'rewards.require_channel',
            'reward_etb' => 'rewards.amount_etb',
            'sports_url' => 'rewards.sport_url',
            'casino_url' => 'rewards.casino_url',
            'support_url' => 'rewards.support_url',
            'brand' => 'rewards.redemption_brand',
        ];
        $values = [];
        foreach ($input as $key => $path) {
            $values[$key] = $settings->get($path);
        }
        $values['enabled'] ??= true;
        $values['withdrawals_enabled'] ??= true;
        $values['require_channel'] ??= true;
        $values['reward_etb'] ??= 2;
        $values['brand'] ??= 'Kemerbet';
        $values['extra_menu_enabled'] = (bool) $settings->get('rewards.extra_menu_enabled', false);
        foreach (['sport', 'casino', 'referral', 'support'] as $name) {
            $values[$name.'_enabled'] = (bool) $settings->get("rewards.menu_{$name}_enabled", true);
            $values[$name.'_label'] = (string) $settings->get("rewards.menu_{$name}_label", ucfirst($name));
        }
        foreach ([50, 100, 500] as $amount) {
            $values['denomination_'.$amount] = in_array($amount, (array) $settings->get('rewards.denominations', [50, 100, 500]), true);
        }
        $this->form->fill($values);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Section::make('Referral and withdrawal rules')->columns(2)->schema([
                Toggle::make('enabled')->label('Enable referral rewards'),
                Toggle::make('withdrawals_enabled')->label('Enable promo-code withdrawals'),
                Toggle::make('require_channel')->label('Require verified channel membership')->helperText('Keep enabled for mandatory join-before-access. Configure channel ID and URL under main Settings.'),
                TextInput::make('reward_etb')->label('Reward per verified new referral (ETB)')->numeric()->integer()->minValue(0)->maxValue(10000)->required(),
                Toggle::make('denomination_50')->label('Allow 50 ETB redemption'),
                Toggle::make('denomination_100')->label('Allow 100 ETB redemption'),
                Toggle::make('denomination_500')->label('Allow 500 ETB redemption'),
                TextInput::make('brand')->label('Promo code redemption account name')->maxLength(80)->required()->helperText('Shown in Telegram after a successful redemption. Default: Kemerbet.'),
            ]),
            Section::make('Main Telegram menu')->columns(2)->schema([
                Toggle::make('sport_enabled')->label('Show Sport button'),
                TextInput::make('sport_label')->label('Sport button label')->maxLength(40)->required(),
                Toggle::make('casino_enabled')->label('Show Casino button'),
                TextInput::make('casino_label')->label('Casino button label')->maxLength(40)->required(),
                Toggle::make('referral_enabled')->label('Show Referral button'),
                TextInput::make('referral_label')->label('Referral button label')->maxLength(40)->required(),
                Toggle::make('support_enabled')->label('Show Support button'),
                Toggle::make('extra_menu_enabled')->label('Show legacy custom menu buttons after the two main rows'),
                TextInput::make('support_label')->label('Support button label')->maxLength(40)->required(),
            ]),
            Section::make('Approved external links')->description('Must be HTTPS. Leave empty to display a safe unavailable/support message.')->schema([
                TextInput::make('sports_url')->label('Sport URL')->url()->maxLength(2048)->nullable(),
                TextInput::make('casino_url')->label('Casino URL')->url()->maxLength(2048)->nullable(),
                TextInput::make('support_url')->label('Support URL')->url()->maxLength(2048)->nullable(),
            ]),
        ]);
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);
        $data = $this->form->getState();
        $settings = app(SettingsService::class);
        foreach ([
            'enabled' => 'rewards.enabled',
            'withdrawals_enabled' => 'rewards.withdrawals_enabled',
            'require_channel' => 'rewards.require_channel',
        ] as $input => $key) {
            $settings->set($key, (bool) ($data[$input] ?? false));
        }
        $settings->set('rewards.amount_etb', (int) ($data['reward_etb'] ?? 2));
        $settings->set('rewards.extra_menu_enabled', (bool) ($data['extra_menu_enabled'] ?? false));
        $settings->set('rewards.redemption_brand', trim((string) ($data['brand'] ?? 'Kemerbet')));
        foreach (['sport', 'casino', 'referral', 'support'] as $name) {
            $settings->set("rewards.menu_{$name}_enabled", (bool) ($data[$name.'_enabled'] ?? false));
            $settings->set("rewards.menu_{$name}_label", trim((string) $data[$name.'_label']));
        }
        foreach (['sport', 'casino', 'support'] as $name) {
            $value = trim((string) ($data[($name === 'sport' ? 'sports' : $name).'_url'] ?? ''));
            abort_if($value !== '' && (! str_starts_with($value, 'https://') || ! filter_var($value, FILTER_VALIDATE_URL)), 422);
            $settings->set("rewards.{$name}_url", $value);
        }
        $denominations = [];
        foreach ([50, 100, 500] as $amount) {
            if ((bool) ($data['denomination_'.$amount] ?? false)) {
                $denominations[] = $amount;
            }
        }
        $settings->set('rewards.denominations', $denominations);
        app(AuditLogger::class)->log('rewards.settings.updated', null, [
            'reward_etb' => $data['reward_etb'] ?? null, 'denominations' => $denominations,
            'withdrawals_enabled' => (bool) ($data['withdrawals_enabled'] ?? false),
        ]);
        Notification::make()->title('Reward settings saved')->success()->send();
    }
}
