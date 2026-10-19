<?php

namespace Database\Seeders\QualificaCatalog;

/**
 * Job roles of the consultancy areas: Gare, GDPR, ISO, Sicurezza, SOA.
 * Format: see JobRoleCatalogue.
 */
final class ConsultancyJobRoles
{
    /**
     * The technical-team lead's duties, word for word the same in Gare and SOA.
     *
     * @var array<int|string, string>
     */
    private const array TECHNICAL_TEAM_LEAD_ACTIVITIES = [
        'Selezione delle risorse del reparto',
        'Pianificazione della formazione del team tecnico' => 'Formazione iniziale in accordo con la direzione e formazione continua del team tecnico.',
        'Formazione e affiancamento dei tecnici',
        'Supervisione delle lavorazioni',
        'Interfaccia nelle comunicazioni tra i settori tecnici',
        'Aggiornamento delle commesse in QNet',
    ];

    /**
     * The certification lead's duties, word for word the same in GDPR and ISO.
     *
     * @var array<int|string, string>
     */
    private const array CERTIFICATION_LEAD_ACTIVITIES = [
        'Coordinamento dei consulenti e controllo delle pratiche in lavorazione',
        'Interfaccia con enti di certificazione e clienti' => 'Per problematiche e chiarimenti.',
        "Preventivi, contrattualistica e supporto all'ufficio gare sui bandi di settore",
        'Controllo della documentazione dei sistemi e gestione dei servizi non conformi',
        'Aggiornamento normativo e ricerca di nuove risorse per il settore',
    ];

    /** @var array<string, array{description: string, activities: array<int|string, string>}> */
    public const array ROLES = [
        'Responsabile Gare' => [
            'description' => 'Responsabile del monitoraggio e del raggiungimento degli obiettivi del settore gare e avvalimenti; motiva e forma il team tecnico.',
            'activities' => [
                'Coordinamento e supervisione delle attività del settore Gare',
                ...self::TECHNICAL_TEAM_LEAD_ACTIVITIES,
            ],
        ],
        'Tecnico Gare' => [
            'description' => "Gestisce il processo di partecipazione a gare d'appalto: dall'analisi delle opportunità alla predisposizione della documentazione, fino al supporto post-aggiudicazione.",
            'activities' => [
                'Preparazione delle gare interne e per i clienti',
                'Ricerca e valutazione dei bandi di gara',
                'Predisposizione della documentazione di gara',
                'Iscrizione agli albi fornitori',
                'Valutazione dei requisiti tecnici e amministrativi di gara',
            ],
        ],
        'Responsabile GDPR/Privacy' => [
            'description' => 'Responsabile della corretta pianificazione e del controllo del settore GDPR; mette in atto le strategie di gestione aziendale e monitora il miglioramento continuo.',
            'activities' => [
                'Gestione delle attività del settore GDPR per clienti privati e comuni',
                ...self::CERTIFICATION_LEAD_ACTIVITIES,
            ],
        ],
        'Consulente GDPR/Privacy' => [
            'description' => 'Adeguamento GDPR e registro dei trattamenti.',
            'activities' => [
                'Gestione e monitoraggio degli adempimenti normativi per le aziende clienti',
                'Consulenza in materia di privacy e protezione dei dati',
                'Compliance GDPR e D.Lgs. 231/2001',
                'Redazione e revisione delle informative privacy',
                'Verifica della conformità documentale e normativa',
            ],
        ],
        'Responsabile ISO' => [
            'description' => 'Responsabile della corretta pianificazione e del controllo del settore ISO-231; mette in atto le strategie di gestione aziendale e monitora il miglioramento continuo.',
            'activities' => [
                'Gestione delle attività del settore ISO-231 per clienti privati e comuni',
                ...self::CERTIFICATION_LEAD_ACTIVITIES,
            ],
        ],
        'Consulente ISO' => [
            'description' => 'Consulenza e assistenza ai clienti sui sistemi di gestione, con supporto durante le verifiche ispettive.',
            'activities' => [
                'Gestione dei clienti ISO assegnati',
                'Supporto durante le verifiche ispettive',
                'Consulenza ISO presso i clienti e da remoto',
                'Redazione e aggiornamento della documentazione ISO',
            ],
        ],
        'Addetto Pianificazione e Controllo' => [
            'description' => 'Calendario degli audit e coordinamento degli enti.',
            'activities' => [
                'Controllo dei report contabili',
                'Pianificazione Ente',
                'Aggiornamento di report e gestionali',
                'Monitoraggio dello stato di avanzamento delle pratiche assegnate',
                'Aggiornamento dello storico pagamenti delle commesse',
                'Pianificazione delle attività dei consulenti',
                'Coordinamento degli auditor',
                'Gestione del calendario degli audit',
                'Monitoraggio delle scadenze',
            ],
        ],
        'Responsabile Sicurezza' => [
            'description' => 'Coordina il settore sicurezza, supervisiona le pratiche e presidia gli incarichi RSPP.',
            'activities' => [
                'Pianificazione e monitoraggio delle attività di consulenza e sopralluogo' => 'Con particolare riferimento agli incarichi RSPP e agli enti pubblici.',
                'Gestione delle relazioni con clienti, fornitori ed enti di controllo' => 'Intervenendo nella risoluzione di problematiche e criticità.',
                'Controllo qualità e validazione della documentazione tecnica' => 'Con supervisione delle pratiche più complesse e strategiche.',
                'Gestione e valutazione dei fornitori e dei partner tecnici' => 'Incluse convenzioni, monitoraggio delle prestazioni e reportistica periodica.',
                'Valutazione tecnica ed economica di gare, commesse e progetti' => 'Verificandone fattibilità e sostenibilità.',
                'Coordinamento e supervisione delle attività del settore Sicurezza' => 'Garantendo il corretto svolgimento dei processi e il supporto tecnico al team.',
                'Coordinamento e sviluppo delle competenze dei tecnici' => 'Monitoraggio delle performance e dei requisiti professionali.',
                'Aggiornamento normativo e diffusione delle novità legislative al team',
            ],
        ],
        'Tecnico Sicurezza' => [
            'description' => 'Gestione operativa delle pratiche di sicurezza sul lavoro e dei clienti assegnati.',
            'activities' => [
                'Erogazione della formazione sulla sicurezza',
                'Gestione dei clienti con contratto RSPP',
                'Gestione dei clienti con contratto di consulenza mensile',
                'Gestione delle visite mediche e dei rapporti con il medico competente',
                'Richiesta e invio dei documenti ai clienti e ricevimento della documentazione firmata',
                'Predisposizione del registro presenze e dei modelli autorizzativi',
                'Redazione degli attestati tramite la piattaforma interna della sicurezza',
                'Gestione dei rapporti con i fornitori di altri servizi del settore sicurezza',
                'Gestione della sicurezza di tutte le sedi e pratiche del gruppo Qualifica',
                'Codifica e archiviazione in cloud della documentazione prodotta nella cartella del cliente',
                'Contatto diretto con il cliente per le informazioni necessarie alla lavorazione' => 'Informando il commerciale.',
                'Redazione dei documenti previsti dal D.Lgs. 81/08',
            ],
        ],
        'Responsabile SOA' => [
            'description' => 'Responsabile della selezione, formazione, pianificazione e controllo delle attività del reparto tecnico SOA.',
            'activities' => [
                ...self::TECHNICAL_TEAM_LEAD_ACTIVITIES,
                'Coordinamento dei pagamenti tra cliente, ente di attestazione e Qualifica',
            ],
        ],
        'Tecnico SOA' => [
            'description' => "Raccolta della documentazione richiesta dall'Organismo di Attestazione e verifica dei requisiti per le categorie e classifiche richieste.",
            'activities' => [
                'Raccolta e verifica della documentazione SOA',
                'Valutazione legale, economica e tecnica interna e dei clienti',
                "Gestione delle pratiche SOA dall'avvio alla conclusione",
                'Studio di fattibilità delle pratiche SOA',
                "Supporto operativo durante l'iter di attestazione",
            ],
        ],
        'Supporto Operativo SOA' => [
            'description' => 'Supporto operativo e documentale alle pratiche del settore SOA.',
            'activities' => [
                'Recupero della documentazione SOA',
            ],
        ],
    ];
}
