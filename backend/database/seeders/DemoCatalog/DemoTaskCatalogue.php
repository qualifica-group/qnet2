<?php

namespace Database\Seeders\DemoCatalog;

/**
 * The DEMO wording of the Tasks module (spec 0101): the phrases DemoTaskSeeder
 * builds its titles and its notes thread from. Pure data — the seeders hold
 * the logic (file-size split, engineering.md §6).
 *
 * Nothing here is a classification: the Task vocabulary (tipologia, categoria,
 * priorita', importanza, stati) is the CLIENT's reference data, owned by
 * QualificaTaskTaxonomySeeder, and a demo seeder must never invent a second
 * one next to it. These are just sentences, in Italian like every other demo
 * dataset, so the module reads as real work rather than as lorem ipsum.
 */
final class DemoTaskCatalogue
{
    /**
     * Root titles, completed with the subject the Task hangs off: the
     * anagrafica it points at, or a fabricated company when it is standalone.
     *
     * @var array<int, string>
     */
    public const array TITLE_TEMPLATES = [
        'Ricontattare %s',
        'Preparare la documentazione per %s',
        'Verificare i requisiti di %s',
        'Sollecitare il rientro del contratto di %s',
        'Pianificare il sopralluogo presso %s',
        'Aggiornare la scheda anagrafica di %s',
        'Inviare il preventivo a %s',
        'Organizzare la riunione di allineamento con %s',
    ];

    /**
     * Sub-task titles: the concrete steps of whatever the parent asks for, so
     * the hierarchy reads as a breakdown and not as a second flat list.
     *
     * @var array<int, string>
     */
    public const array SUBTASK_TITLES = [
        'Raccogliere i documenti mancanti',
        'Preparare la bozza da rivedere',
        'Confrontarsi con il referente',
        'Aggiornare il fascicolo condiviso',
        'Chiudere la checklist interna',
    ];

    /**
     * Opening posts of a Task thread: what someone writes when they pick the
     * activity up.
     *
     * @var array<int, string>
     */
    public const array NOTE_BODIES = [
        'Ho sentito il referente: ci ricontatta entro la settimana con i documenti mancanti.',
        'Attenzione alle scadenze: la documentazione va protocollata prima di fine mese.',
        'Aggiornamento: prima bozza pronta, manca la revisione dei costi.',
        'Il cliente ha chiesto di posticipare l\'incontro, propongo la settimana prossima.',
        'Verificato con l\'amministrazione, il fascicolo risulta completo.',
        'Restano da chiarire due punti sul perimetro, li porto in riunione.',
    ];

    /**
     * Replies to an opening post, kept short: they are answers, not second
     * openings.
     *
     * @var array<int, string>
     */
    public const array NOTE_REPLIES = [
        'Confermo, procedo io con il sollecito.',
        'Va bene, allineiamoci domani mattina.',
        'Ho aggiornato il fascicolo con l\'ultima versione.',
        'Segnalo che manca ancora la firma sul modulo.',
    ];
}
