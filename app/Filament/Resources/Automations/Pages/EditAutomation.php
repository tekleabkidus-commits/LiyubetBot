<?php

namespace App\Filament\Resources\Automations\Pages;

use App\Filament\Resources\Automations\AutomationResource;
use App\Filament\Resources\Automations\Support\SavesAutomationSteps;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditAutomation extends EditRecord
{
    use SavesAutomationSteps;

    protected static string $resource = AutomationResource::class;

    protected ?bool $hasDatabaseTransactions = true;

    private array $stepsData = [];

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->modalDescription('Deleting an automation also deletes all of its enrollments.'),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['steps_data'] = $this->fillStepsData($this->record);

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if ($this->record->userStates()->where('status', 'active')->exists()) {
            // Media, message and button updates are safe for future deliveries,
            // but existing enrollments need stable step numbers and timing.
            $incoming = array_values($data['steps_data'] ?? []);
            $existing = array_values($this->fillStepsData($this->record));

            if (count($incoming) !== count($existing)) {
                throw ValidationException::withMessages([
                    'data.steps_data' => 'This journey has active participants. You may edit existing messages, images and buttons, but cannot add or remove steps.',
                ]);
            }

            foreach ($incoming as $index => $step) {
                if ((int) ($step['delay_hours'] ?? 0) !== (int) ($existing[$index]['delay_hours'] ?? 0)) {
                    throw ValidationException::withMessages([
                        'data.steps_data' => 'This journey has active participants. Step delays cannot be changed until the participants finish.',
                    ]);
                }
            }
        }
        $this->stepsData = $data['steps_data'] ?? [];
        unset($data['steps_data']);

        return $data;
    }

    protected function afterSave(): void
    {
        $this->saveSteps($this->record, $this->stepsData);
    }
}
