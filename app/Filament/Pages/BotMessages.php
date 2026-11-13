<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Support\FormMedia;
use App\Models\MediaFile;
use App\Services\AuditLogger;
use App\Services\Bot\BotMessageCatalog;
use App\Services\Bot\BotMessageService;
use App\Services\Bot\TokenRenderer;
use App\Services\MediaFileService;
use BackedEnum;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use UnitEnum;

class BotMessages extends Page
{
    protected string $view = 'filament.pages.bot-messages';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';
    protected static string|UnitEnum|null $navigationGroup = 'Bot Content';
    protected static ?string $navigationLabel = 'Bot Messages';
    protected static ?int $navigationSort = 1;

    public string $selectedKey = 'welcome';

    /** @var array<string,mixed> */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user() !== null && ! auth()->user()->isViewer();
    }

    public function mount(): void
    {
        $this->loadMessage();
    }

    public function selectMessage(string $key): void
    {
        abort_unless(static::canAccess(), 403);
        abort_unless(BotMessageCatalog::get($key) !== null, 404);

        $this->selectedKey = $key;
        $this->loadMessage();
    }

    private function loadMessage(): void
    {
        $content = app(BotMessageService::class)->content($this->selectedKey);
        $media = $content['media_file_id'] === null
            ? null : MediaFile::query()->find($content['media_file_id']);

        $this->form->fill([
            'text' => $content['text'],
            'media_upload' => $media?->path,
            'enabled' => $content['enabled'],
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $row = BotMessageCatalog::get($this->selectedKey) ?? BotMessageCatalog::get('welcome');

        return $schema->statePath('data')->components([
            Section::make('Message content')
                ->description('You are editing one message only. Updates take effect for future Telegram replies.')
                ->schema([
                    Textarea::make('text')
                        ->label('Message text')
                        ->rows(10)
                        ->required()
                        ->maxLength(4096)
                        ->helperText('Amharic and English are both supported. Keep variable tokens in braces (for example {first_name} and {referral_link}). Use a normal line break for a new line.'),
                ]),
            Section::make('Message image')
                ->description('Optional image for this particular message. Uploading a new file replaces its previous attachment.')
                ->schema([
                    FileUpload::make('media_upload')
                        ->label('Image')
                        ->image()
                        ->imagePreviewHeight('180')
                        ->disk(config('filesystems.default'))
                        ->directory('media/bot-messages')
                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                        ->maxSize(6 * 1024)
                        ->helperText('JPEG, PNG or WebP, maximum 6 MB. Use persistent S3-compatible storage on Laravel Cloud for durable files. Removing the image from this field removes it from this message, not from prior Telegram chats.'),
                ]),
            Section::make('Delivery settings')
                ->visible((bool) ($row['optional'] ?? false))
                ->schema([
                    Toggle::make('enabled')
                        ->label('Send this message after the welcome')
                        ->helperText('Keep disabled until the promotional wording and offer are approved.')
                        ->inline(false),
                ]),
        ]);
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);
        $row = BotMessageCatalog::get($this->selectedKey);
        abort_unless($row !== null, 404);

        $state = $this->form->getState();
        $text = trim((string) ($state['text'] ?? ''));

        if ($text === '' || mb_strlen($text) > 4096) {
            $this->addError('data.text', 'Message text must contain 1–4096 characters.');
            return;
        }

        $current = app(BotMessageService::class)->content($this->selectedKey);
        $currentMedia = $current['media_file_id'] !== null
            ? MediaFile::query()->find($current['media_file_id']) : null;
        $newMedia = filled($state['media_upload'] ?? null)
            ? FormMedia::resolveMediaFileId($state['media_upload'], $currentMedia)
            : null;

        app(BotMessageService::class)->set($this->selectedKey, [
            'text' => $text,
            'media_file_id' => $newMedia,
            'enabled' => (bool) ($state['enabled'] ?? false),
        ]);

        app(AuditLogger::class)->log('bot.message.updated', null, [
            'message_key' => $this->selectedKey,
            'image_attached' => $newMedia !== null,
            'enabled' => ($row['optional'] ?? false) ? (bool) ($state['enabled'] ?? false) : true,
        ]);

        $this->loadMessage();
        Notification::make()->title($row['title'].' saved')->body('This message will be used for future bot replies.')->success()->send();
    }

    public function resetEditor(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->loadMessage();
    }

    /** Sample values only; no live customer data or promo codes in previews. */
    protected function getViewData(): array
    {
        $current = BotMessageCatalog::get($this->selectedKey) ?? BotMessageCatalog::get('welcome');
        $previewText = (string) ($this->data['text'] ?? '');
        $sample = [
            'first_name'=>'Abel',
            'referral_link'=>'https://t.me/liyubetsbot?start=ref_123',
            'reward_amount'=>'2',
            'brand'=>'Kemerbet',
            'referrals'=>'12',
            'qualified'=>'10',
            'balance'=>'20.00',
            'earned'=>'40.00',
            'redeemed'=>'20.00',
            'withdrawals'=>'1',
            'status_note'=>'',
            'code'=>'EXAMPLE-NOT-A-REAL-CODE',
            'amount'=>'50',
            'count'=>'1',
            'entries'=>'Sample transaction',
            'id'=>'123',
            'status'=>'issued',
            'date'=>'2026-11-13 12:00',
            'section'=>'Sport',
        ];
        $rendered = app(TokenRenderer::class)->render($previewText, $sample);
        $content = app(BotMessageService::class)->content($this->selectedKey);

        return [
            'groups' => BotMessageCatalog::groups(),
            'selectedMessage' => $current,
            'previewText' => $rendered,
            'currentMedia' => $content['media_file_id'] === null ? null : MediaFile::query()->find($content['media_file_id']),
            'messageCount' => count(BotMessageCatalog::all()),
        ];
    }
}
