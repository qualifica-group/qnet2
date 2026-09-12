<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\NotificationLevelEnum;

/**
 * Voce 10 della Mappa Notifiche di DOC Tasks.docx (spec 0119).
 *
 * Evento: TaskActionService::block().
 * Destinatari: creatore, richiedente, assegnatari e osservatori.
 * Canali: mail, database.
 */
class TaskLocked extends TaskNotification
{
    protected function level(): NotificationLevelEnum
    {
        return NotificationLevelEnum::Warning;
    }

    protected function title(): string
    {
        return __('Task blocked');
    }

    protected function body(): string
    {
        return __(':actor blocked the task ":title".', [
            'actor' => $this->actorName(),
            'title' => $this->taskTitle(),
        ]);
    }
}
