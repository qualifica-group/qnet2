<?php

declare(strict_types=1);

namespace App\Notifications;

/**
 * Voce 7 della Mappa Notifiche di DOC Tasks.docx (spec 0119).
 *
 * Evento: TaskService::create(), e TaskService::update() per i soli assegnatari AGGIUNTI (D-9).
 * Destinatari: gli assegnatari alla creazione; su PATCH i soli aggiunti.
 * Canali: mail, database.
 *
 * D-3 ha un effetto visibile proprio qui: un Task creato da chi ne e' anche
 * l'unico assegnatario non produce alcuna notifica, perche' l'attore e' il
 * solo destinatario e viene escluso.
 */
class TaskAssigned extends TaskNotification
{
    protected function title(): string
    {
        return __('Task assigned to you');
    }

    protected function body(): string
    {
        return __(':actor assigned you the task ":title".', [
            'actor' => $this->actorName(),
            'title' => $this->taskTitle(),
        ]);
    }
}
