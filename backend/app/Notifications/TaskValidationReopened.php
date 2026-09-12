<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\NotificationLevelEnum;

/**
 * Voce 6 della Mappa Notifiche di DOC Tasks.docx (spec 0119).
 *
 * Evento: uncomplete() quando la fase DI PARTENZA e' `in_validation`, letta prima della scrittura (D-12).
 * Destinatari: il richiedente e il creatore. Nessun ripiego D-5: il creatore e' gia' destinatario di suo, quindi un richiedente nullo contribuisce semplicemente nessuno.
 * Canali: mail, database.
 */
class TaskValidationReopened extends TaskNotification
{
    protected function level(): NotificationLevelEnum
    {
        return NotificationLevelEnum::Warning;
    }

    protected function title(): string
    {
        return __('Task back in progress');
    }

    protected function body(): string
    {
        return __(':actor withdrew the task ":title" from validation, which is back in progress.', [
            'actor' => $this->actorName(),
            'title' => $this->taskTitle(),
        ]);
    }
}
