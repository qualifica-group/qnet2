<?php

namespace Database\Seeders\ManagementControl;

use App\Enums\CostTypeNature;

/**
 * The fixed vocabulary of the management control dataset (DemoManagementControlSeeder):
 * the period, the offer it sells (demo products by name), the suppliers, the structure
 * costs and the payroll levels. Italian, fictional names only.
 */
final class McCatalogue
{
    public const string PERIOD_START = '2025-01-01';

    public const string PERIOD_END = '2026-10-31';

    public const int FAKER_SEED = 20250101;

    public const int CUSTOMERS = 100;

    /** New opportunities per month, before the seasonal factor. */
    public const int DEALS_PER_MONTH = 18;

    /** August and December sell less. */
    public const array SEASONAL_FACTOR = [8 => 0.4, 12 => 0.6, 7 => 0.8];

    public const int WIN_PERCENT = 55;

    public const string TITLE_PREFIX = 'CdG - ';

    /** Typology of the sold products: the company sells in its own name, the revenue is the net amount. */
    public const string SOLD_TYPOLOGY_CODE = 'consultancy';

    /** Training: courses sold by participant, one quote line = one edition. Name => [category, price per participant]. */
    public const array TRAINING_PRODUCTS = [
        'Formazione Sicurezza Lavoratori (D.Lgs. 81/08)' => ['Corsi in Aula', 320.00],
        'Corso Excel Avanzato in azienda' => ['Corsi in Aula', 650.00],
        'Corso Project Management avanzato' => ['Corsi in Aula', 1150.00],
        'Corso Digital Marketing in e-learning' => ['Corsi Online', 450.00],
        'Corso Inglese Business in e-learning' => ['Corsi Online', 620.00],
    ];

    /** Consulting: one quote line = one project phase, invoiced at its end (SAL). Name => [category, area, price per phase]. */
    public const array CONSULTING_PRODUCTS = [
        'Assessment delle competenze manageriali' => ['Consulenza HR', 'hr', 2600.00],
        'Progetto Welfare Aziendale' => ['Consulenza HR', 'hr', 5200.00],
        'Audit Sicurezza Informatica (NIS2)' => ['Consulenza IT', 'it', 4300.00],
        'Progetto Migrazione Cloud' => ['Consulenza IT', 'it', 8700.00],
    ];

    public const array PHASES = [
        'hr' => ['Analisi organizzativa', 'Interviste e questionari', 'Report e piano di azione', 'Accompagnamento al cambiamento', 'Verifica dei risultati'],
        'it' => ['Analisi infrastruttura', 'Progettazione', 'Implementazione', 'Test e collaudo', 'Avvio in esercizio e supporto'],
    ];

    public const array CITIES = ['Milano', 'Torino', 'Bologna', 'Verona', 'Padova', 'Brescia', 'Bergamo', 'Firenze', 'Roma', 'Napoli', 'Bari', 'Genova'];

    /** Cost products of the demo catalogue used on quotes, work order costs and orders. */
    public const string COST_TEACHER = 'Docente esterno';

    public const string COST_ROOM = 'Affitto aula';

    public const string COST_MATERIAL = 'Materiale didattico';

    public const string COST_TRAIN = 'Biglietto treno Alta Velocita';

    public const string COST_HOTEL = 'Pernottamento hotel';

    public const string COST_MEAL = 'Pasto';

    /** Payment methods offered to customers, by name (split into installments by the system). */
    public const array CUSTOMER_PAYMENT_METHODS = [
        'Bonifico bancario', 'RiBa 30/60 gg DF', 'RiBa 30/60/90 gg DF', 'Bonifico 60/90/120 gg DF', 'Bonifico 30/60/90 gg DF - IVA alla prima rata',
    ];

    public const string SUPPLIER_PAYMENT_METHOD = 'Bonifico bancario';

    /** Share of customers that pay late or not at all (overdue receivables). */
    public const int LATE_PAYER_PERCENT = 12;

    /** Cost types the dataset needs beyond the demo ones: [name, nature, color]. */
    public const array COST_TYPES = [
        'Docenze e subappalti' => [CostTypeNature::Direct, '#2563eb'],
        'Trasferte' => [CostTypeNature::Direct, '#0891b2'],
        'Affitti e locazioni' => [CostTypeNature::General, '#7c3aed'],
        'Utenze' => [CostTypeNature::General, '#ca8a04'],
        'Software e licenze' => [CostTypeNature::General, '#4f46e5'],
        'Consulenze professionali' => [CostTypeNature::General, '#db2777'],
        'Servizi generali' => [CostTypeNature::Indirect, '#65a30d'],
        'Marketing e fiere' => [CostTypeNature::Indirect, '#ea580c'],
        'Noleggi e leasing' => [CostTypeNature::Indirect, '#475569'],
        'Assicurazioni' => [CostTypeNature::General, '#0f766e'],
    ];

    /** Suppliers: name => [VAT number, city]. */
    public const array SUPPLIERS = [
        'Docenti Associati Soc. Coop.' => ['04567890123', 'Milano'],
        'Formatori Italiani S.r.l.' => ['05678901234', 'Bologna'],
        'Centro Congressi Lombardo S.r.l.' => ['06789012345', 'Milano'],
        'Aule e Spazi Emilia S.r.l.' => ['07890123456', 'Bologna'],
        'Tecnologie Cloud Adriatiche S.r.l.' => ['08901234567', 'Pescara'],
        'Sistemi e Reti Piemonte S.p.A.' => ['09012345678', 'Torino'],
        'Immobiliare Corso Venezia S.r.l.' => ['10123456789', 'Milano'],
        'Energia Padana S.p.A.' => ['11234567890', 'Brescia'],
        'TeleLink Italia S.r.l.' => ['12345678901', 'Roma'],
        'Software House Tirrenica S.r.l.' => ['13456789012', 'Livorno'],
        'Studio Bianchi e Associati' => ['14567890123', 'Milano'],
        'Pulizie Brillanti S.n.c.' => ['15678901234', 'Monza'],
        'Assicurazioni Alpine S.p.A.' => ['16789012345', 'Trento'],
        'Eventi e Fiere Nord S.r.l.' => ['17890123456', 'Verona'],
        'Noleggio Auto Lombardia S.p.A.' => ['18901234567', 'Bergamo'],
        'Grafiche Venete S.n.c.' => ['19012345678', 'Padova'],
        'Informatica Ufficio S.r.l.' => ['20123456789', 'Varese'],
    ];

    /** Supplier of the external teachers / of the rooms / of the IT subcontracting. */
    public const array TEACHER_SUPPLIERS = ['Docenti Associati Soc. Coop.', 'Formatori Italiani S.r.l.'];

    public const array ROOM_SUPPLIERS = ['Centro Congressi Lombardo S.r.l.', 'Aule e Spazi Emilia S.r.l.'];

    public const array IT_SUPPLIERS = ['Tecnologie Cloud Adriatiche S.r.l.', 'Sistemi e Reti Piemonte S.p.A.'];

    /**
     * Recurring structure costs, no work order: [supplier, cost type, description, monthly amount,
     * months it is billed (null = every month), seasonal amplitude].
     */
    public const array STRUCTURE_COSTS = [
        ['Immobiliare Corso Venezia S.r.l.', 'Affitti e locazioni', 'Canone di locazione sede di Milano', 4200.00, null, 0.0],
        ['Energia Padana S.p.A.', 'Utenze', 'Fornitura energia elettrica bimestrale', 1150.00, [2, 4, 6, 8, 10, 12], 0.35],
        ['TeleLink Italia S.r.l.', 'Utenze', 'Telefonia fissa, mobile e connettivita fibra', 390.00, null, 0.0],
        ['Software House Tirrenica S.r.l.', 'Software e licenze', 'Canone gestionale e licenze cloud', 1180.00, null, 0.0],
        ['Software House Tirrenica S.r.l.', 'Software e licenze', 'Rinnovo annuale licenze e assistenza', 6800.00, [1], 0.0],
        ['Studio Bianchi e Associati', 'Consulenze professionali', 'Consulenza fiscale, contabile ed elaborazione paghe', 1650.00, null, 0.0],
        ['Pulizie Brillanti S.n.c.', 'Servizi generali', 'Servizio di pulizia uffici', 640.00, null, 0.0],
        ['Assicurazioni Alpine S.p.A.', 'Assicurazioni', 'Polizza RC professionale e multirischio ufficio', 5400.00, [2], 0.0],
        ['Eventi e Fiere Nord S.r.l.', 'Marketing e fiere', 'Partecipazione a fiera di settore e materiali promozionali', 5200.00, [3, 6, 10], 0.25],
        ['Noleggio Auto Lombardia S.p.A.', 'Noleggi e leasing', 'Noleggio a lungo termine auto aziendali (2 vetture)', 1040.00, null, 0.0],
    ];

    /** Purchase requests (RDA -> ODA) of the structure, quarterly: [subject, cost type, supplier, [[description, quantity, price]]]. */
    public const array STRUCTURE_REQUESTS = [
        ['Dotazione informatica nuovi assunti', 'Software e licenze', 'Informatica Ufficio S.r.l.', [['Portatile 14 pollici', 2, 980.00], ['Monitor 27 pollici', 2, 260.00]]],
        ['Materiali promozionali e brochure', 'Marketing e fiere', 'Grafiche Venete S.n.c.', [['Stampa brochure istituzionali', 500, 1.40], ['Roll-up per eventi', 2, 145.00]]],
        ['Cancelleria e consumabili', 'Servizi generali', 'Informatica Ufficio S.r.l.', [['Toner stampante', 6, 72.00], ['Risme carta A4', 40, 4.60]]],
    ];

    /** Monthly gross salary by business function name, [min, max]; others use the default. */
    public const array SALARY_BY_FUNCTION = [
        'Direzione Generale' => [5200, 7200],
        'Amministrazione, Finanza e Controllo' => [2300, 3300],
        'Commerciale e Vendite' => [2400, 3600],
        'Sistemi Informativi (IT)' => [2600, 3700],
        'Produzione' => [2100, 3100],
        'Ricerca e Sviluppo' => [2700, 3800],
    ];

    public const array DEFAULT_SALARY = [1950, 2900];

    /** CCNL renewal on 2026-01-01: the raise in percent applied to everyone hired before it. */
    public const string RAISE_ON = '2026-01-01';

    public const float RAISE_PERCENT = 2.8;

    /** Functions whose people deliver the commesse (hours on work orders). */
    public const array DELIVERY_FUNCTIONS = ['Produzione', 'Sistemi Informativi (IT)', 'Ricerca e Sviluppo', 'Qualità', 'Sicurezza e Ambiente (HSE)', 'Assistenza Clienti', 'Risorse Umane'];

    /** Generic activities of the hours with no commessa, by kind. */
    public const array GENERIC_ACTIVITIES = [
        'Riunione di coordinamento interno', 'Attivita amministrativa e reportistica', 'Formazione interna e aggiornamento',
        'Supporto commerciale a offerte', 'Gestione documentale e qualita', 'Pianificazione attivita settimanali',
    ];

    /** Italian bank holidays (month-day) plus Easter Mondays of the period. */
    public const array HOLIDAYS = ['01-01', '01-06', '04-25', '05-01', '06-02', '08-15', '11-01', '12-08', '12-25', '12-26'];

    public const array EASTER_MONDAYS = ['2025-04-21', '2026-04-06'];

    /** Company closure weeks: no hours at all. */
    public const array CLOSURES = [['2025-08-11', '2025-08-22'], ['2025-12-24', '2026-01-06'], ['2026-08-10', '2026-08-21']];

    /** Agents (commercial recipients) and reporters, Italian names. */
    public const array AGENTS = ['Giorgio Lombardi', 'Federica Marchetti', 'Stefano Bellini', 'Chiara Fontana', 'Paolo Ricci'];

    public const array REPORTERS = ['Luca Ferretti', 'Alessia Santoro'];

    /** Commission rules by category name: [commercial %, reporter %]. */
    public const array COMMISSION_RULES = [
        'Corsi in Aula' => [8.0, 2.0],
        'Corsi Online' => [10.0, 2.0],
        'Consulenza HR' => [6.0, 1.5],
        'Consulenza IT' => [5.0, 1.5],
    ];
}
