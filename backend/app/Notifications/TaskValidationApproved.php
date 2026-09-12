<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\NotificationLevelEnum;

/**
 * Voce 4 della Mappa Notifiche di DOC Tasks.docx (spec 0119).
 *
 * Evento: TaskActionService::approve() su un Task in validazione.
 * Destinatari: gli assegnatari del Task.
 * Canali: mail, database.
 *
 * E' anche la "email di conferma" che il documento chiede alla voce "Valida
 * attivita' -> Confermare": non serve una dodicesima classe, e' questa.
 */
class TaskValidationApproved extends TaskNotification
{
    protected function level(): NotificationLevelEnum
    {
        return NotificationLevelEnum::Success;
    }

    protected function title(): string
    {
        return __('Validation approved');
    }

    protected function body(): string
    {
        return __(':actor approved the validation of the task ":title".', [
            'actor' => $this->actorName(),
            'title' => $this->taskTitle(),
        ]);
    }
}
