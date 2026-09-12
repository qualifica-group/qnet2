<?php

declare(strict_types=1);

namespace App\Notifications;

/**
 * Voce 8 della Mappa Notifiche di DOC Tasks.docx (spec 0119).
 *
 * Evento: TaskService::create(), e TaskService::update() per i soli osservatori AGGIUNTI (D-9).
 * Destinatari: gli osservatori alla creazione; su PATCH i soli aggiunti.
 * Canali: mail, database.
 */
class TaskObserver extends TaskNotification
{
    protected function title(): string
    {
        return __('You are following a task');
    }

    protected function body(): string
    {
        return __(':actor added you as a watcher of the task ":title".', [
            'actor' => $this->actorName(),
            'title' => $this->taskTitle(),
        ]);
    }
}
