<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\NotificationLevelEnum;

/**
 * Voce 9 della Mappa Notifiche di DOC Tasks.docx (spec 0119).
 *
 * Evento: uncomplete() quando la fase DI PARTENZA e' di chiusura (D-12).
 * Destinatari: creatore, richiedente, assegnatari e osservatori.
 * Canali: mail, database.
 *
 * Il nome porta la C maiuscola in mezzo perche' cosi' lo scrive il documento
 * di prodotto, e perche' finisce nella colonna `type` della tabella
 * `notifications`: e' un dato persistito, non un dettaglio estetico (D-1).
 */
class TaskUnCompleted extends TaskNotification
{
    protected function level(): NotificationLevelEnum
    {
        return NotificationLevelEnum::Warning;
    }

    protected function title(): string
    {
        return __('Task reopened');
    }

    protected function body(): string
    {
        return __(':actor reopened the task ":title".', [
            'actor' => $this->actorName(),
            'title' => $this->taskTitle(),
        ]);
    }
}
