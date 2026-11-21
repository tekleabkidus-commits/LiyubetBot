<?php

declare(strict_types=1);

use App\Enums\MediaKind;
use App\Filament\Resources\Automations\Support\SavesAutomationSteps;
use App\Models\Automation;
use App\Models\AutomationUserState;
use App\Models\MediaFile;
use App\Models\User;
use App\Services\Automations\AutomationRunner;
use Database\Seeders\SettingsSeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    $this->seed(SettingsSeeder::class);
    Storage::fake(config('filesystems.default'));
});

function automationImageStepEditor(): object
{
    return new class {
        use SavesAutomationSteps {
            saveSteps as public;
            fillStepsData as public;
        }
    };
}

function putAutomationTestImage(string $path = 'media/automations/photo.png'): string
{
    // A valid 1x1 PNG, so both MIME detection and test upload storage work.
    Storage::disk()->put($path, base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRuoAAAAASUVORK5CYII='
    ));

    return $path;
}

it('stores an image in an automation step, reloads it for editing and sends it via Telegram', function () {
    $path = putAutomationTestImage();
    $automation = Automation::factory()->create();

    automationImageStepEditor()->saveSteps($automation, [[
        'delay_hours' => 1,
        'text_en' => 'Special for {first_name}',
        'media_upload' => $path,
        'buttons' => [[
            'kind' => 'url',
            'url' => 'https://example.test',
            'label' => ['en' => 'See more'],
        ]],
    ]]);

    $step = $automation->steps()->with(['mediaFile', 'translations'])->firstOrFail();

    expect($step->mediaFile)->not->toBeNull()
        ->and($step->mediaFile->kind)->toBe(MediaKind::Photo)
        ->and($step->mediaFile->path)->toBe($path)
        ->and(automationImageStepEditor()->fillStepsData($automation->fresh()->load('steps.mediaFile', 'steps.translations'))[0]['media_upload'])->toBe($path);

    $user = User::factory()->create();
    AutomationUserState::factory()->create([
        'automation_id' => $automation->id,
        'user_id' => $user->id,
        'current_step_no' => 0,
        'next_step_at' => now()->subMinute(),
    ]);

    app(AutomationRunner::class)->runDue();

    $message = fakeTelegram()->lastSentTo($user->tg_chat_id);

    expect($message['method'])->toBe('sendPhoto')
        ->and($message['params']['caption'])->toBe('Special for '.$user->first_name)
        ->and($message['params']['reply_markup']['inline_keyboard'][0][0]['text'])->toBe('See more')
        ->and($step->refresh()->sent_count)->toBe(1)
        ->and($step->mediaFile->refresh()->tg_file_id)->not->toBeNull();
});

it('allows image-only automation steps with no text caption', function () {
    $path = putAutomationTestImage();
    $automation = Automation::factory()->create();

    automationImageStepEditor()->saveSteps($automation, [[
        'delay_hours' => 1,
        'text_en' => '',
        'media_upload' => $path,
        'buttons' => [],
    ]]);

    expect($automation->steps()->first()->translations()->count())->toBe(0);

    $user = User::factory()->create();
    AutomationUserState::factory()->create([
        'automation_id' => $automation->id,
        'user_id' => $user->id,
        'current_step_no' => 0,
        'next_step_at' => now()->subMinute(),
    ]);

    app(AutomationRunner::class)->runDue();

    expect(fakeTelegram()->lastSentTo($user->tg_chat_id)['method'])->toBe('sendPhoto');
});

it('reuses an existing media record on edit and detaches removed automation images', function () {
    $path = putAutomationTestImage();
    $automation = Automation::factory()->create();
    $editor = automationImageStepEditor();
    $row = [
        'delay_hours' => 2,
        'text_en' => 'Image update',
        'media_upload' => $path,
        'buttons' => [],
    ];

    $editor->saveSteps($automation, [$row]);
    $id = $automation->steps()->first()->media_file_id;
    $editor->saveSteps($automation, [$row]);

    expect(MediaFile::query()->count())->toBe(1)
        ->and($automation->steps()->first()->media_file_id)->toBe($id);

    $editor->saveSteps($automation, [[...$row, 'media_upload' => null]]);

    expect($automation->steps()->first()->media_file_id)->toBeNull()
        ->and(MediaFile::query()->count())->toBe(1);
});
