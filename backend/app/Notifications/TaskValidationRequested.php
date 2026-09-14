<?php

declare(strict_types=1);

namespace App\Notifications;

/**
 * Voce 1 della Mappa Notifiche di DOC Tasks.docx (spec 0119).
 *
 * Evento: TaskCompletionService::complete() con `validation_status_id` inviato (CASO 2 del documento).
 * Destinatari: il richiedente del Task. E' l'UNICA voce in cui il richiedente e' il solo destinatario previsto, e quindi la sola in cui D-5 lascia subentrare il creatore quando `requester_id` e' null: senza quel ripiego una richiesta di validazione su una delle righe storiche prive di richiedente non raggiungerebbe nessuno e il Task resterebbe in validazione per sempre.
 * Canali: mail, database.
 */
class TaskValidationRequested extends TaskNotification
{
    protected function title(): string
    {
        return __('Validation requested');
    }

    protected function body(): string
    {
        return __(':actor completed the task ":title" and asked you to validate it.', [
            'actor' => $this->actorName(),
            'title' => $this->taskTitle(),
        ]);
    }
}
