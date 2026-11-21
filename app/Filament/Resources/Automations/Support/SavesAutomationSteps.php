<?php

namespace App\Filament\Resources\Automations\Support;

use App\Enums\BotLanguage;
use App\Filament\Support\FormMedia;
use App\Models\Automation;

/**
 * Maps the steps repeater to automation_steps rows keyed by step_no, so a
 * step edited in place keeps its sent_count and stays aligned with in-flight
 * automation_user_states positions.
 */
trait SavesAutomationSteps
{
    protected function saveSteps(Automation $automation, array $stepsData): void
    {
        // Live journeys can update step content. EditAutomation disallows
        // adding/removing steps or changing step delays while users are active.
        $keptStepNos = [];

        foreach (array_values($stepsData) as $index => $row) {
            $keptStepNos[] = $index;

            $step = $automation->steps()->updateOrCreate(
                ['step_no' => $index],
                [
                    'delay_hours' => (int) ($row['delay_hours'] ?? 0),
                    'buttons' => filled($row['buttons'] ?? null) ? array_values($row['buttons']) : null,
                ],
            );

            // Resolve the stored upload against the shared media_files table.
            // Keep Telegram file_id caching for an unchanged image and detach
            // the media when the admin clears the upload.
            $mediaFileId = FormMedia::resolveMediaFileId(
                $row['media_upload'] ?? null,
                $step->mediaFile,
            );

            if ($step->media_file_id !== $mediaFileId) {
                $step->update(['media_file_id' => $mediaFileId]);
            }

            foreach ([BotLanguage::En] as $lang) {
                $text = $row['text_'.$lang->value] ?? null;

                if (filled($text)) {
                    $step->translations()->updateOrCreate(['lang' => $lang->value], ['text' => $text]);
                } else {
                    $step->translations()->where('lang', $lang->value)->delete();
                }
            }
        }

        $automation->steps()->whereNotIn('step_no', $keptStepNos)->delete();
    }

    protected function fillStepsData(Automation $automation): array
    {
        return $automation->steps->map(fn ($step) => [
            'delay_hours' => $step->delay_hours,
            'text_en' => $step->translations->firstWhere('lang', BotLanguage::En)?->text,
            'media_upload' => $step->mediaFile?->path,
            'buttons' => $step->buttons ?? [],
        ])->all();
    }
}
