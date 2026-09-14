<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\NotificationLevelEnum;

/**
 * Voce 5 della Mappa Notifiche di DOC Tasks.docx (spec 0119).
 *
 * Evento: TaskCompletionService::reject() su un Task in validazione.
 * Destinatari: gli assegnatari del Task.
 * Canali: mail, database.
 */
class TaskValidationRejected extends TaskNotification
{
    protected function level(): NotificationLevelEnum
    {
        return NotificationLevelEnum::Warning;
    }

    protected function title(): string
    {
        return __('Validation rejected');
    }

    protected function body(): string
    {
        return __(':actor rejected the validation of the task ":title", which is back in progress.', [
            'actor' => $this->actorName(),
            'title' => $this->taskTitle(),
        ]);
    }
}
