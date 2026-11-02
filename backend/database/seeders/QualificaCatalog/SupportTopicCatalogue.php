<?php

namespace Database\Seeders\QualificaCatalog;

/**
 * The client's IT support request types (spec 0334): what a user picks in the
 * "Assistenza tecnica" dialog, each turned into a Task for the IT department.
 * Lookups are matched by NAME on QualificaTaskTaxonomySeeder's rows; icons
 * come from App\Support\BadgeTokens::icons().
 */
final class SupportTopicCatalogue
{
    /** Who receives every request (user directive 2026-10-30). */
    public const string ASSIGNEE_EMAIL = 'nicola.eliseo@qualificagroup.com';

    public const string TASK_TYPE = 'Ticket';

    public const string TASK_CATEGORY = 'Tecnico (informatico)';

    public const string TASK_PRIORITY = 'Media';

    public const string URGENT_TASK_PRIORITY = 'Urgente';

    public const string TASK_IMPORTANCE = 'Media';

    public const int DUE_DAYS = 2;

    /** An urgent request is due the same day. */
    public const int URGENT_DUE_DAYS = 0;

    /**
     * Name, description shown to the user, icon — in display order.
     *
     * @var array<int, array{0: string, 1: string, 2: string}>
     */
    public const array TOPICS = [
        ['Stampante', 'Non stampa, carta inceppata, stampante non trovata', 'printer'],
        ['PC o portatile', 'Lento, non si accende, schermo, tastiera o mouse', 'laptop'],
        ['Password e accesso', 'Password dimenticata, account bloccato, non riesco ad entrare', 'key'],
        ['Email', 'Le email non arrivano o non partono, casella piena', 'mail'],
        ['Rete e internet', 'Internet lento o assente, Wi-Fi, VPN', 'wifi'],
        ['Telefono', 'Telefono fisso o cellulare aziendale', 'smartphone'],
        ['QNet', 'Errori o problemi nell\'uso di QNet', 'monitor'],
        ['Altro', 'Qualsiasi altro problema tecnico', 'wrench'],
    ];
}
