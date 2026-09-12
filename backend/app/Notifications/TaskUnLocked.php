<?php

declare(strict_types=1);

namespace App\Notifications;

/**
 * Voce 11 della Mappa Notifiche di DOC Tasks.docx (spec 0119).
 *
 * Evento: TaskActionService::unblock().
 * Destinatari: creatore, richiedente, assegnatari e osservatori.
 * Canali: mail, database.
 *
 * Come TaskUnCompleted, la L maiuscola in mezzo e' quella del documento (D-1).
 */
class TaskUnLocked extends TaskNotification
{
    protected function title(): string
    {
        return __('Task unblocked');
    }

    protected function body(): string
    {
        return __(':actor unblocked the task ":title".', [
            'actor' => $this->actorName(),
            'title' => $this->taskTitle(),
        ]);
    }
}
