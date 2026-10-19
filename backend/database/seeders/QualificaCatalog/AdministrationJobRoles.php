<?php

namespace Database\Seeders\QualificaCatalog;

/**
 * Job roles of the staff areas: A&C, HR, IT, Gestione Sedi, Qualità,
 * Segreteria, Servizi Generali, UPPCG. Format: see JobRoleCatalogue.
 */
final class AdministrationJobRoles
{
    /** @var array<string, array{description: string, activities: array<int|string, string>}> */
    public const array ROLES = [
        'Commercialista Esterno' => [
            'description' => "Responsabile degli adempimenti contabili e fiscali dell'azienda; non ha responsabilità gerarchica sul team interno.",
            'activities' => [
                'Revisione contabile',
                'Supporto alle attività di chiusura bilancio',
            ],
        ],
        'CFO' => [
            'description' => 'Direzione amministrativa e finanziaria, affidata in outsourcing.',
            'activities' => [
                'Gestione delle attività di tesoreria',
                'Supporto nella gestione bancaria',
                'Monitoraggio dei flussi finanziari e dei pagamenti',
            ],
        ],
        'Supporto Operativo Amministrazione' => [
            'description' => "Supporto operativo all'amministrazione: situazione clienti e fornitori, prima nota, pagamenti di docenti e discenti.",
            'activities' => [
                'Gestione della situazione clienti e fornitori',
                'Tenuta della prima nota',
                'Registrazione delle notule di docenti e discenti e gestione dei relativi pagamenti',
            ],
        ],
        'Coordinatore Contabilità' => [
            'description' => "Coordina e supervisiona le attività quotidiane dell'area contabile, senza responsabilità gerarchica sul settore.",
            'activities' => [
                'Coordinamento e supervisione delle attività contabili',
                'Coordinamento della riconciliazione bancaria',
                'Supporto alle attività di chiusura bilancio',
                "Verifica delle ritenute d'acconto e delle relative contabilizzazioni",
                'Supervisione della rendicontazione attiva e passiva',
                'Supervisione delle liquidazioni IVA mensili e trimestrali',
                'Gestione delle fatture emesse verso la Pubblica Amministrazione' => 'Regioni, Ministero del Lavoro e altri enti.',
            ],
        ],
        'Addetto Contabilità Fornitori e IVA' => [
            'description' => 'Gestione del ciclo passivo e degli adempimenti IVA periodici.',
            'activities' => [
                'Predisposizione delle liquidazioni IVA mensili e trimestrali',
                'Supporto nelle registrazioni contabili periodiche',
                'Registrazione delle fatture ricevute',
            ],
        ],
        'Addetto Recupero Crediti e Fatturazione' => [
            'description' => 'Gestione del ciclo attivo: fatturazione, monitoraggio incassi e recupero crediti.',
            'activities' => [
                'Monitoraggio degli incassi, solleciti e recupero crediti' => 'Monitoraggio di incassi e scadenze, solleciti e recupero dei pagamenti di clienti aziendali e corsi autofinanziati.',
                'Emissione di proforme e fatture, anche per i corsi autofinanziati',
                'Registrazione del costo del personale',
                'Registrazione dei movimenti bancari e riconciliazioni',
                'Gestione delle rendicontazioni di partner e sedi',
                'Supporto amministrativo ai settori aziendali',
            ],
        ],
        'HR Manager / Responsabile Settore HR' => [
            'description' => "Responsabile del settore e dell'ufficio Risorse Umane.",
            'activities' => [
                "Coordinamento dell'Ufficio HR e definizione di organigramma e ruoli" => "Coordinamento organizzativo dell'Ufficio HR e supervisione organizzativa delle risorse.",
                'Pianificazione e gestione dei fabbisogni di personale',
                'Supporto alle selezioni strategiche e ai colloqui finali',
                'Onboarding dei nuovi assunti',
                'Gestione quotidiana dei dipendenti' => 'Supporto ai lavoratori, richieste individuali, comunicazioni interne, monitoraggio di assenze e produttività, trasferte e rimborsi.',
                'Formazione e sviluppo del personale' => 'Pianificazione della formazione obbligatoria e professionale, valutazione delle performance e dei KPI, sviluppo delle competenze.',
                'Gestione delle criticità interne',
                'Gestione delle contestazioni disciplinari' => 'Con il supporto del Consulente del lavoro.',
                'Gestione delle conciliazioni',
                'Interfaccia con i responsabili di settore e con la direzione',
                'Reporting HR e supporto alle decisioni della Direzione',
                'Monitoraggio delle scadenze',
            ],
        ],
        'HR Generalist' => [
            'description' => "Supporto operativo all'HR Manager su amministrazione, documentazione e gestione del personale; gestione dell'intero processo di ricerca e selezione del personale interno.",
            'activities' => [
                'Onboarding dei nuovi assunti',
                "Supporto operativo all'HR Manager",
                'Pianificazione e gestione dei fabbisogni di personale',
                'Gestione delle timbrature tramite Dipendenti in Cloud',
                'Gestione delle visite mediche dei dipendenti',
                'Supporto al Consulente del lavoro su assunzioni, proroghe e trasformazioni' => 'Con estrazione di elenchi e report su Zucchetti.',
                'Gestione e archiviazione della documentazione dei dipendenti',
                'Reporting HR e supporto alle decisioni della Direzione',
                'Monitoraggio delle scadenze',
                'Ricerca e selezione del personale interno',
            ],
        ],
        'Consulente del lavoro' => [
            'description' => 'Elaborazione dei cedolini e gestione contributiva.',
            'activities' => [
                'Amministrazione del personale e gestione dei contratti di lavoro' => 'Assunzioni, proroghe, trasformazioni e cessazioni.',
                'Gestione delle comunicazioni obbligatorie',
                'Elaborazione di cedolini e riepiloghi, gestione presenze e LUL',
                'Gestione di contributi INPS/INAIL, imposte, TFR, fondi pensione e welfare',
                'Gestione dei cassetti previdenziali e assicurativi INPS e INAIL',
                'Elaborazioni mensili F24 e UNIEMENS',
                'Pagamento degli F24 di tutte le società come intermediario autorizzato',
                'Elaborazioni annuali CU, 770 e autoliquidazione INAIL',
                'Monitoraggio del DURC delle società',
                'Gestione normativa e compliance del lavoro' => 'Applicazione del CCNL, aggiornamento normativo, contenzioso e disciplinare, rapporti con gli enti, verifica di orari, straordinari e trasferte, supporto in caso di ispezioni.',
                'Gestione delle conciliazioni',
                'Budget del personale e analisi dei costi del lavoro',
                'Reporting HR e supporto alle decisioni della Direzione',
                'Monitoraggio delle scadenze',
            ],
        ],
        'Responsabile Sistemi e Supporto Utenti' => [
            'description' => "Responsabile dell'unità Sistemi e Supporto Utenti: infrastruttura, sicurezza informatica e dotazioni aziendali.",
            'activities' => [
                "Coordinamento e supervisione dell'unità Sistemi e Supporto Utenti",
                "Gestione dell'infrastruttura IT e della sicurezza informatica",
                'Gestione di account, licenze e caselle di posta aziendali' => 'Creazione e configurazione degli utenti sul tenant Microsoft 365, assegnazione delle licenze e gestione delle caselle Exchange.',
                'Configurazione di PC, telefoni e dispositivi aziendali' => 'Configurazione dei dispositivi in Entra Join e gestione dei profili utente.',
                'Supporto tecnico agli utenti' => 'Problematiche di sistema, anche da remoto tramite NinjaOne (sistema operativo, driver, connettività e software di base); escluse le attività da application specialist su software gestionali o applicativi specifici.',
            ],
        ],
        'Tecnico Sistemi e Supporto Utenti' => [
            'description' => 'Supporto tecnico agli utenti e gestione operativa di dispositivi e account aziendali. Le attività sul tenant Microsoft 365 si svolgono su assegnazione esplicita e previa autorizzazione del responsabile di riferimento.',
            'activities' => [
                'Supporto tecnico agli utenti',
                'Gestione di account, licenze e caselle di posta aziendali',
                'Configurazione di PC, telefoni e dispositivi aziendali',
                'Messa in sicurezza operativa di base' => 'Password policy, permessi, accessi.',
                'Redazione di documentazione tecnica IT su richiesta',
            ],
        ],
        'Responsabile Sviluppo Software' => [
            'description' => "Responsabile dell'unità Sviluppo Software e Intelligenza Artificiale.",
            'activities' => [
                "Coordinamento e supervisione dell'unità Sviluppo Software",
                'Gestione della programmazione e dello sviluppo software',
                'Gestione dei progetti di intelligenza artificiale',
            ],
        ],
        'Sviluppatore Software' => [
            'description' => 'Sviluppo software e progetti di intelligenza artificiale, a supporto del Responsabile Sviluppo Software.',
            'activities' => [
                'Sviluppo e manutenzione del gestionale QNet',
                'Sviluppo e manutenzione degli applicativi per enti e clienti' => 'Software per i Comuni, Formalab e altri gestionali.',
                'Sviluppo del software RUP',
                'Progettazione e sviluppo di soluzioni di intelligenza artificiale',
            ],
        ],
        'Responsabile Gestione Sedi' => [
            'description' => 'Responsabile del presidio e del corretto funzionamento delle sedi, con particolare riferimento alle sedi esterne.',
            'activities' => [
                'Coordinamento della gestione e del presidio delle sedi',
                'Check periodici da remoto sullo stato delle sedi esterne' => 'Tramite videochiamate di verifica.',
                'Definizione e monitoraggio delle checklist delle sedi esterne' => 'Le checklist che i referenti delle sedi esterne devono compilare.',
                'Gestione delle segnalazioni di guasti e dei fabbisogni di fornitura delle sedi esterne',
                'Verifica di postazione e dotazioni per le nuove risorse delle sedi esterne',
            ],
        ],
        'Operativo Gestione Sedi' => [
            'description' => 'Gestione operativa delle attività relative alle sedi, con particolare riferimento alle sedi esterne.',
            'activities' => [
                'Check periodici da remoto sullo stato delle sedi esterne',
                'Raccolta e controllo delle checklist compilate dalle sedi esterne',
                'Attivazione e gestione da remoto degli interventi per guasti nelle sedi esterne',
                'Approvvigionamento e invio di materiali e attrezzature alle sedi esterne',
                'Verifica di postazione e dotazioni per le nuove risorse delle sedi esterne',
                'Gestione delle rendicontazioni di partner e sedi',
                'Supporto alla contabilità sulle attività relative ai partner',
            ],
        ],
        'Responsabile Qualità' => [
            'description' => 'Gestione del sistema qualità e degli audit interni.',
            'activities' => [
                'Supervisione delle procedure aziendali',
                'Gestione delle non conformità',
                'Coordinamento del Sistema di Gestione Qualità',
                'Verifica della corretta applicazione delle procedure',
                'Monitoraggio della conformità normativa',
                'Audit interni',
            ],
        ],
        'Addetto Segreteria' => [
            'description' => 'Supporto operativo di segreteria e front-office.',
            'activities' => [
                'Commissioni esterne (posta, banca e simili)',
                "Gestione della documentazione e dell'archivio",
                'Gestione della corrispondenza (e-mail e PEC) e delle telefonate',
                'Organizzazione di riunioni e trasferte e gestione delle spese',
                "Supporto all'ufficio acquisti",
            ],
        ],
        'Responsabile Servizi Generali' => [
            'description' => 'Coordinamento e pianificazione dei servizi generali e della manutenzione delle sedi.',
            'activities' => [
                'Coordinamento e pianificazione dei servizi generali',
            ],
        ],
        'Addetto Pulizie e Manutenzione' => [
            'description' => 'Pulizia e manutenzione ordinaria delle sedi e approvvigionamento dei materiali di consumo.',
            'activities' => [
                'Pulizia della sede principale e delle sedi esterne',
                'Approvvigionamento dei materiali di consumo',
                'Guida delle navette aziendali',
                'Manutenzione ordinaria degli uffici',
            ],
        ],
        'Responsabile UPPCG' => [
            'description' => 'Presidia la pianificazione, il monitoraggio dei settori e la gestione degli acquisti.',
            'activities' => [
                'Incontri periodici con i settori aziendali',
                'Gestione e risoluzione delle criticità dei settori',
                'Monitoraggio dei settori aziendali',
                'Gestione degli acquisti (RDA)',
            ],
        ],
        'Supporto Operativo UPPCG' => [
            'description' => 'Pianificazione e coordinamento generale delle attività.',
            'activities' => [
                'Gestione degli acquisti (RDA)',
                'Supporto alle attività del Responsabile UPPCG',
            ],
        ],
    ];
}
