<?php

declare(strict_types=1);

namespace App\Notifications;

/**
 * Voce 2 della Mappa Notifiche di DOC Tasks.docx (spec 0119).
 *
 * Evento: complete() senza `validation_status_id` e con `closure_feedback` vuoto, oppure approve() su un Task il cui feedback e' rimasto vuoto.
 * Destinatari: creatore, richiedente, assegnatari e osservatori (D-4).
 * Canali: mail, database.
 *
 * D-4: il documento si contraddice sui destinatari del completamento — la
 * sezione "Azioni" scrive "creatore, assegnatari e osservatori", la Mappa
 * Notifiche scrive "richiedente + osservatori". Per decisione dell'utente
 * (2026-09-11) vince l'UNIONE dei due insiemi, cosi' questa voce e la 3
 * condividono l'audience con le voci 9, 10 e 11.
 */
class TaskClosed extends TaskNotification
{
    protected function title(): string
    {
        return __('Task completed');
    }

    protected function body(): string
    {
        return __(':actor completed the task ":title".', [
            'actor' => $this->actorName(),
            'title' => $this->taskTitle(),
        ]);
    }
}
