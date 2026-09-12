<?php

declare(strict_types=1);

namespace App\Notifications;

/**
 * Voce 3 della Mappa Notifiche di DOC Tasks.docx (spec 0119).
 *
 * Evento: complete() senza `validation_status_id` e con `closure_feedback` valorizzato. La biforcazione la decide il write path, mai una seconda valutazione qui dentro (D-12).
 * Destinatari: creatore, richiedente, assegnatari e osservatori (D-4), gli stessi della voce 2.
 * Canali: mail, database.
 */
class TaskFeedbackInserted extends TaskNotification
{
    protected function title(): string
    {
        return __('Task completed with feedback');
    }

    protected function body(): string
    {
        return __(':actor completed the task ":title" and left a closing feedback.', [
            'actor' => $this->actorName(),
            'title' => $this->taskTitle(),
        ]);
    }
}
