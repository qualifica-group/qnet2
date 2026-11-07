<?php

namespace Database\Seeders\QualificaCatalog;

/**
 * The open "trattative" of APL Orientamento specialistico, from the user's sheet
 * "APL_Orientamento Specialistico Trattative 06_11_26.xlsx" (user directive
 * 2026-11-07: a plain seeder, not the import wizard). Cleaned at the source, like
 * any data sender: names split surname/first name by hand, phones reduced to
 * digits (one entry per number), dates as Y-m-d, every value spelled exactly as
 * the record it points to (Fonte, Stato, Comune, Segnalatore, Commerciale).
 *
 * User decisions 2026-11-07: "Non Risponde" (absent from the workflow) becomes
 * "Da Richiamare" with the note "Non risponde"; "Data App CPI"/"CPI" go to the
 * Informazioni aggiuntive `data_scelta_cpi`/`cpi`; the row without a name gets
 * the placeholder "Contatto <phone>"; the Segnalatori are the existing referents
 * below, the two unknown ones are created (NEW_REPORTERS). A row with
 * `transferred_from` is born already transferred from that Sede.
 */
final class AplOrientationRequestCatalogue
{
    public const string CATEGORY = 'Orientamento specialistico';

    public const string PRODUCT = 'Orientamento specialistico';

    /** Fonte of a row whose sheet cell is empty (created by the user). */
    public const string FALLBACK_SOURCE = 'No fonte';

    /** Reporters missing from the referents: name => [first name, last name]. */
    public const array NEW_REPORTERS = [
        'Deborah Green' => ['Deborah', 'Green'],
        'Svitlana Nikolaychuk' => ['Svitlana', 'Nikolaychuk'],
    ];

    private const string NO_ANSWER = 'Non risponde';

    /**
     * One entry per sheet row (2..78). Keys: operator, status, source, first, last,
     * phones, callback, cpi_date, cpi, birth_date, tax_code, email, city,
     * reporter, commercial, transferred_from, notes.
     *
     * @var list<array<string, mixed>>
     */
    public const array ROWS = [
        ['operator' => 'Giulia Costanzo', 'status' => 'Attesa _ App. CPI', 'source' => 'Diretto', 'first' => 'Chiara', 'last' => 'Longo', 'phones' => ['3343086056'], 'callback' => '2026-11-30', 'birth_date' => '2001-11-21', 'tax_code' => 'LNGCHR01S61F839G', 'email' => 'longochiara59@gmail.com', 'city' => 'Naples', 'cpi' => 'NA _ NA _ Scampia'],
        ['operator' => 'Anna Garofalo', 'status' => 'Da Richiamare', 'source' => 'Passaparola', 'first' => 'Mariarosaria', 'last' => 'Spino', 'phones' => ['3333142068'], 'callback' => '2026-04-08'],
        ['operator' => 'Anna Garofalo', 'status' => 'Da Richiamare', 'source' => 'Passaparola', 'first' => 'Claudia', 'last' => 'Della Monica', 'phones' => ['3342021981'], 'callback' => '2026-04-01'],
        ['operator' => 'Anna Garofalo', 'status' => 'Da Richiamare', 'source' => 'Passaparola', 'first' => 'Michelina', 'last' => 'Pascale', 'phones' => ['3299336978'], 'callback' => '2026-06-17', 'email' => 'tombaantonietta1@gmail.com', 'transferred_from' => 'Catania'],
        ['operator' => 'Marco Fedele', 'status' => 'Attesa esito SFL/ADI', 'source' => 'Passaparola', 'first' => 'Maria Angela', 'last' => 'Campanile', 'phones' => ['3920410771']],
        ['operator' => 'Marco Fedele', 'status' => 'Attesa esito SFL/ADI', 'source' => 'Passaparola', 'first' => 'Rosa', 'last' => 'Lazzaro', 'phones' => ['3930457794']],
        ['operator' => 'Anna Garofalo', 'status' => 'Da Richiamare', 'source' => 'Passaparola', 'first' => 'Marianna', 'last' => 'Topa', 'phones' => ['3701532455'], 'callback' => '2026-04-28'],
        ['operator' => 'Vincenzo Crisci', 'status' => 'OK App. Fissato CPI', 'source' => 'Passaparola', 'first' => 'Rosa', 'last' => 'De Filippo', 'phones' => ['3930588007']],
        ['operator' => 'Anna Garofalo', 'status' => 'OK App. Fissato CPI', 'source' => 'Passaparola', 'first' => 'Vincenzo', 'last' => 'Ferrara', 'phones' => ['3510501646'], 'callback' => '2026-04-28', 'cpi_date' => '2026-06-29', 'tax_code' => 'FRRVCN94S02F839L', 'email' => 'liberolibero1926@hotmail.com', 'city' => 'Naples', 'cpi' => 'Napoli Nord', 'reporter' => 'Deborah Green'],
        ['operator' => 'Anna Garofalo', 'status' => 'Da Richiamare', 'source' => 'Social', 'first' => 'Pompeo', 'last' => 'Sica', 'phones' => ['3277118659'], 'callback' => '2026-04-14', 'birth_date' => '1966-03-21', 'tax_code' => 'SCIPMP66C21F839B', 'email' => 'sicapompeo@libero.it', 'city' => 'Naples', 'cpi' => 'Napoli Centro'],
        ['operator' => 'Anna Garofalo', 'status' => 'Attesa _ App. CPI', 'source' => 'Passaparola', 'first' => 'Veronica', 'last' => 'Visconti', 'phones' => ['3451577249'], 'callback' => '2026-04-14', 'email' => 'veronik1@hotmail.it', 'city' => 'Aversa', 'cpi' => 'Aversa'],
        ['operator' => 'Francesca Forgione', 'status' => 'Da Richiamare', 'source' => 'Passaparola', 'first' => 'Carmela', 'last' => 'Barbato', 'phones' => ['3713680108'], 'callback' => '2026-11-30'],
        ['operator' => 'Anna Garofalo', 'status' => 'Da Richiamare', 'source' => 'Social', 'first' => 'Daniela', 'last' => 'Argiolas', 'phones' => ['3408511158'], 'callback' => '2026-04-07', 'birth_date' => '1968-05-27', 'tax_code' => 'RGLDNL68E67B745M', 'email' => 'danielaargiolas045@gmail.com', 'city' => 'Succivo', 'cpi' => 'Aversa Sede temporanea'],
        ['operator' => 'Anna Garofalo', 'status' => 'Da Richiamare', 'source' => 'Passaparola', 'first' => 'Rosa', 'last' => 'Palumbo', 'phones' => ['3471774236'], 'callback' => '2026-05-05', 'cpi_date' => '2026-04-09'],
        ['operator' => 'Giulia Costanzo', 'status' => 'OK App. Fissato CPI', 'source' => 'Derivanti da CAF e Segnalazioni', 'first' => 'Nunzia', 'last' => 'Maisto', 'phones' => ['3331186347'], 'callback' => '2026-11-11', 'cpi_date' => '2026-11-11', 'birth_date' => '2003-10-22', 'tax_code' => 'MSTNNZ03R62F839X', 'email' => 'nunziamaisto03@icloud.com', 'city' => 'Grumo Nevano', 'cpi' => 'Frattamaggiore', 'reporter' => 'grumo nevano caf', 'commercial' => 'Biagio Fusco'],
        ['operator' => 'Anna Garofalo', 'status' => 'Da Richiamare', 'source' => 'Passaparola', 'first' => 'Sergio', 'last' => 'Mazza', 'phones' => ['3886204226'], 'callback' => '2026-04-20'],
        ['operator' => 'Anna Garofalo', 'status' => 'Da Richiamare', 'source' => 'Passaparola', 'first' => 'Anna', 'last' => 'Sciammarella', 'phones' => ['3498199400'], 'callback' => '2026-04-27'],
        ['operator' => 'Anna Garofalo', 'status' => 'Da Richiamare', 'source' => 'Passaparola', 'first' => 'Sefora', 'last' => 'Sannino', 'phones' => ['3511803040'], 'callback' => '2026-05-13', 'notes' => self::NO_ANSWER],
        ['operator' => 'Marco Fedele', 'status' => 'Attesa esito SFL/ADI', 'source' => 'Derivanti da CAF e Segnalazioni', 'first' => 'Francesco', 'last' => "D'Ambra", 'phones' => ['3274611355'], 'reporter' => 'RAFFAELLA CAF PATRONATO AFRAGOLA', 'commercial' => 'Marco Fedele'],
        ['operator' => 'Anna Garofalo', 'status' => 'Da Richiamare', 'source' => 'Derivanti da CAF e Segnalazioni', 'first' => 'Elena', 'last' => 'Feraru', 'phones' => ['3533139600'], 'callback' => '2026-05-05', 'cpi_date' => '2026-05-04', 'reporter' => '. CAF MULTISERVIZI PALLADINO_FRATTAMAGGIORE'],
        ['operator' => 'Anna Garofalo', 'status' => 'Da Richiamare', 'source' => 'Passaparola', 'first' => null, 'last' => 'Contatto 3791567705', 'phones' => ['3791567705']],
        ['operator' => 'Giulia Costanzo', 'status' => 'Da Richiamare', 'source' => 'Passaparola', 'first' => 'Rosa', 'last' => 'Ruggiero', 'phones' => ['3467179427'], 'callback' => '2026-11-13'],
        ['operator' => 'Giulia Costanzo', 'status' => 'Da Richiamare', 'source' => 'Passaparola', 'first' => 'Natasha Linda', 'last' => 'Carbone', 'phones' => ['3792981789']],
        ['operator' => 'Valentina Scala', 'status' => 'OK App. Fissato CPI', 'source' => 'Derivanti da CAF e Segnalazioni', 'first' => 'Gina', 'last' => 'Pezzella', 'phones' => ['3933120934'], 'callback' => '2026-11-10', 'cpi_date' => '2026-11-04', 'birth_date' => '1974-03-09', 'tax_code' => 'PZZGNI74C49D789D', 'cpi' => 'Frattamaggiore', 'reporter' => '. CAF COLDIRETTI_CARDITO', 'commercial' => 'Biagio Fusco'],
        ['operator' => 'Vincenzo Crisci', 'status' => 'Da Richiamare', 'source' => 'Centralino', 'first' => 'Maria Giovanna', 'last' => 'Deleo', 'phones' => ['3317451618'], 'callback' => '2026-09-18'],
        ['operator' => 'Vincenzo Crisci', 'status' => 'Da Richiamare', 'source' => 'Derivanti da CAF e Segnalazioni', 'first' => 'Tonia', 'last' => 'Santoro', 'phones' => ['3515943607'], 'callback' => '2026-09-21', 'email' => 'toniasantoro48677@gmail.com', 'reporter' => 'Caivano CAF SENAS', 'commercial' => 'Vincenzo Crisci'],
        ['operator' => 'Vincenzo Crisci', 'status' => 'Da Richiamare', 'source' => 'Derivanti da CAF e Segnalazioni', 'first' => 'Sara', 'last' => 'Cozzolino', 'phones' => ['3663209593'], 'callback' => '2026-09-21', 'email' => 'sara04cozzolino@gmail.com', 'reporter' => 'Caivano CAF SENAS', 'commercial' => 'Vincenzo Crisci'],
        ['operator' => 'Anna Garofalo', 'status' => 'OK App. Fissato CPI', 'source' => 'Diretto / Passaparola', 'first' => 'Pietro', 'last' => "D'Alesio", 'phones' => ['3496774225'], 'callback' => '2026-11-17', 'cpi_date' => '2026-11-19', 'reporter' => 'Svitlana Nikolaychuk'],
        ['operator' => 'Valentina Scala', 'status' => 'OK App. Fissato CPI', 'source' => 'Sito', 'first' => 'Antonia', 'last' => 'Ambrosio', 'phones' => ['3773857759'], 'callback' => '2026-11-18', 'cpi_date' => '2026-11-19', 'email' => 'toniaambrosio66@gmail.com', 'cpi' => 'Frattamaggiore'],
        ['operator' => 'Anna Garofalo', 'status' => 'Da Richiamare', 'source' => 'Sito', 'first' => 'Lorenza', 'last' => 'Verde', 'phones' => ['3714216155'], 'callback' => '2026-12-09'],
        ['operator' => 'Giulia Costanzo', 'status' => 'OK App. Fissato CPI', 'source' => 'Passaparola', 'first' => 'Olimpia', 'last' => 'Di Domenico', 'phones' => ['3714894001'], 'callback' => '2026-11-27', 'cpi_date' => '2027-01-11'],
        ['operator' => 'Anna Garofalo', 'status' => 'Attesa esito SFL/ADI', 'source' => 'Passaparola', 'first' => 'Luisa', 'last' => "D'Aquale", 'phones' => ['3510264350'], 'callback' => '2026-10-20', 'cpi_date' => '2026-12-02'],
        ['operator' => 'Anna Garofalo', 'status' => 'Da Richiamare', 'source' => 'Sito', 'first' => 'Francesca', 'last' => 'Diana', 'phones' => ['3313009203'], 'callback' => '2026-10-06'],
        ['operator' => 'Giulia Costanzo', 'status' => 'Da Richiamare', 'source' => 'Passaparola', 'first' => null, 'last' => 'Di Maso', 'phones' => ['3762019053', '3311521347'], 'callback' => '2026-12-02', 'notes' => self::NO_ANSWER],
        ['operator' => 'Simone Seneca', 'status' => 'OK App. Fissato CPI', 'source' => 'Passaparola', 'first' => 'Giuseppina', 'last' => null, 'phones' => ['3200778285'], 'callback' => '2026-12-23', 'cpi_date' => '2026-12-28'],
        ['operator' => 'Valentina Scala', 'status' => 'OK App. Fissato CPI', 'source' => 'Centralino', 'first' => 'Rosa', 'last' => 'Storico', 'phones' => ['3334879377'], 'callback' => '2026-12-23', 'cpi_date' => '2026-12-23', 'city' => 'Caivano', 'cpi' => 'Frattamaggiore'],
        ['operator' => 'Giulia Costanzo', 'status' => 'Da Richiamare', 'source' => 'Sito', 'first' => 'Caterina', 'last' => 'Del Prete', 'phones' => ['3487617943'], 'callback' => '2027-02-01'],
        ['operator' => 'Simone Seneca', 'status' => 'OK App. Fissato CPI', 'source' => 'Centralino', 'first' => 'Martina', 'last' => 'Della Rocca', 'phones' => ['3511688461'], 'callback' => '2026-12-21', 'cpi_date' => '2026-12-21'],
        ['operator' => 'Anna Garofalo', 'status' => 'Da Richiamare', 'source' => 'Centralino', 'first' => 'Angela', 'last' => 'Rullo', 'phones' => ['3285359300'], 'tax_code' => 'RLLNGL83E57E791P', 'email' => 'angela.rullo83@gmail.com', 'city' => 'Crispano', 'cpi' => 'Frattamaggiore'],
        ['operator' => 'Simone Seneca', 'status' => 'Attesa esito SFL/ADI', 'source' => 'Passaparola', 'first' => 'Antony', 'last' => 'De Lucia', 'phones' => ['3509339785'], 'callback' => '2026-11-09'],
        ['operator' => 'Anna Garofalo', 'status' => 'Da Richiamare', 'source' => 'Centralino', 'first' => 'Carmela', 'last' => 'Di Giuseppe', 'phones' => ['3533050230'], 'callback' => '2026-10-05'],
        ['operator' => 'Simone Seneca', 'status' => 'OK App. Fissato CPI', 'source' => 'Sito', 'first' => 'Maria Pia', 'last' => 'Castiello', 'phones' => ['3662074852'], 'callback' => '2026-11-30', 'cpi_date' => '2026-11-30'],
        ['operator' => 'Gaetano Della Porta', 'status' => 'Da Richiamare', 'source' => 'Sito', 'first' => 'Stefania', 'last' => 'Bava', 'phones' => ['3349288752'], 'callback' => '2026-11-26'],
        ['operator' => 'Gaetano Della Porta', 'status' => 'Da Richiamare', 'source' => 'Centralino', 'first' => 'Nunzia', 'last' => 'Scaraglia', 'phones' => ['3791697574'], 'callback' => '2026-12-14'],
        ['operator' => 'Valentina Scala', 'status' => 'OK App. Fissato CPI', 'source' => 'Sito', 'first' => 'Noemi', 'last' => 'Lettiero', 'phones' => ['3388414165', '3665944777'], 'callback' => '2027-01-15', 'cpi_date' => '2027-01-15'],
        ['operator' => 'Francesca Forgione', 'status' => 'Da Richiamare', 'source' => 'Sito', 'first' => 'Antonietta', 'last' => 'Altezza', 'phones' => ['3509138647'], 'callback' => '2026-11-09', 'notes' => self::NO_ANSWER],
        ['operator' => 'Anna Garofalo', 'status' => 'Da Richiamare', 'source' => 'Sito', 'first' => 'Maria', 'last' => 'Vilardi', 'phones' => ['3278116747'], 'callback' => '2026-10-20'],
        ['operator' => 'Anna Garofalo', 'status' => 'Attesa _ App. CPI', 'source' => 'Centralino', 'first' => 'Anita', 'last' => 'Setaro', 'phones' => ['3914820818'], 'callback' => '2026-10-15'],
        ['operator' => 'Valentina Scala', 'status' => 'Da Richiamare', 'source' => 'Centralino', 'first' => 'Carmela', 'last' => 'Russo', 'phones' => ['3311115299']],
        ['operator' => 'Simone Seneca', 'status' => 'OK App. Fissato CPI', 'source' => 'Centralino', 'first' => 'Imma', 'last' => 'Belardo', 'phones' => ['3384119548'], 'callback' => '2027-01-11'],
        ['operator' => 'Valentina Scala', 'status' => 'OK App. Fissato CPI', 'source' => 'Centralino', 'first' => 'Salvatore', 'last' => 'De Luca', 'phones' => ['3511169252'], 'callback' => '2027-01-08', 'cpi_date' => '2027-01-08', 'city' => 'Afragola'],
        ['operator' => 'Gaetano Della Porta', 'status' => 'Da Richiamare', 'source' => 'Sito', 'first' => 'Luisa', 'last' => "D'Andrea", 'phones' => ['3384557787'], 'callback' => '2027-01-11'],
        ['operator' => 'Francesca Forgione', 'status' => 'Da Richiamare', 'source' => 'Passaparola', 'first' => 'Carmela', 'last' => 'Cavaliere', 'phones' => ['3381511678'], 'callback' => '2026-11-09'],
        ['operator' => 'Anna Garofalo', 'status' => 'Da Richiamare', 'source' => 'Centralino', 'first' => 'Pio', 'last' => 'Sepe', 'phones' => ['3801562334'], 'callback' => '2026-10-30'],
        ['operator' => 'Lea Pellegrino', 'status' => 'Da Richiamare', 'source' => 'Sito', 'first' => 'Gaetano', 'last' => 'Cirillo', 'phones' => ['3294249839', '3298189569'], 'callback' => '2026-11-10', 'email' => 'gaetanocirillo111@gmail.com'],
        ['operator' => 'Simone Seneca', 'status' => 'Attesa _ App. CPI', 'source' => 'Derivanti da CAF e Segnalazioni', 'first' => 'Vincenzo', 'last' => 'Basile', 'phones' => ['3277572202'], 'callback' => '2026-11-10', 'reporter' => 'NUNZIA/LEA CAF AFRAGOLA/PASQUALE'],
        ['operator' => 'Valentina Scala', 'status' => 'OK App. Fissato CPI', 'source' => 'Centralino', 'first' => 'Rosa', 'last' => 'Grimaldi', 'phones' => ['3481606536'], 'callback' => '2026-11-11', 'cpi_date' => '2026-12-24'],
        ['operator' => 'Giulia Costanzo', 'status' => 'Da Richiamare', 'source' => 'Sito', 'first' => 'Anna', 'last' => 'Di Dona', 'phones' => ['3272248176'], 'callback' => '2026-12-02', 'email' => 'didonaanna61@gmail.com', 'city' => "Sant'Antimo"],
        ['operator' => 'Giulia Costanzo', 'status' => 'Da Richiamare', 'source' => 'Centralino', 'first' => 'Diego', 'last' => 'Silvestro', 'phones' => ['3501313532'], 'callback' => '2026-11-09', 'city' => 'Arzano'],
        ['operator' => 'Simone Seneca', 'status' => 'Da Richiamare', 'source' => 'Centralino', 'first' => 'Angela', 'last' => 'Russo', 'phones' => ['3405364083'], 'callback' => '2026-11-09', 'city' => 'Frattaminore'],
        ['operator' => 'Giulia Costanzo', 'status' => 'Da Richiamare', 'source' => 'Sito', 'first' => 'Antonia', 'last' => 'Chiacchio', 'phones' => ['3271764452'], 'callback' => '2026-11-30'],
        ['operator' => 'Simone Seneca', 'status' => 'Attesa _ App. CPI', 'source' => 'Centralino', 'first' => 'Giuseppina', 'last' => 'Calabria', 'phones' => ['3515545717'], 'callback' => '2026-11-09', 'city' => 'Acerra'],
        ['operator' => 'Lea Pellegrino', 'status' => 'Da Richiamare', 'source' => 'Centralino', 'first' => 'Salvatore', 'last' => 'Santaniello', 'phones' => ['3275335016'], 'callback' => '2026-11-09', 'city' => 'Caivano'],
        ['operator' => 'Valentina Scala', 'status' => 'Da Richiamare', 'source' => 'Centralino', 'first' => 'Elia', 'last' => "D'Avanzo", 'phones' => ['3804992936'], 'callback' => '2026-11-11', 'email' => 'eliadavanzo2020@gmail.com'],
        ['operator' => 'Giulia Costanzo', 'status' => 'Da Richiamare', 'source' => 'Centralino', 'first' => 'Anna', 'last' => 'Balsamo', 'phones' => ['3661625103'], 'callback' => '2026-11-09'],
        ['operator' => 'Francesca Forgione', 'status' => 'OK App. Fissato CPI', 'source' => 'Passaparola', 'first' => 'Federica', 'last' => 'Mangiacapre', 'phones' => ['3249873583'], 'callback' => '2026-11-09', 'cpi_date' => '2027-01-18'],
        ['operator' => 'Anna Garofalo', 'status' => 'Da Richiamare', 'source' => 'Centralino', 'first' => 'Rosaria', 'last' => 'Vergara', 'phones' => ['3482367490'], 'callback' => '2026-11-10', 'city' => 'Frattamaggiore'],
        ['operator' => 'Simone Seneca', 'status' => 'Attesa _ App. CPI', 'source' => 'Derivanti da CAF e Segnalazioni', 'first' => 'Alessio', 'last' => "D'Ambrosio", 'phones' => ['3511787155'], 'callback' => '2026-11-16', 'reporter' => 'NUNZIA/LEA CAF AFRAGOLA/PASQUALE'],
        ['operator' => 'Gaetano Della Porta', 'status' => 'Da Richiamare', 'source' => 'Sito', 'first' => 'Viviana', 'last' => "D'Agostino", 'phones' => ['3271608150'], 'callback' => '2026-11-09'],
        ['operator' => 'Simone Seneca', 'status' => 'Da Richiamare', 'source' => 'Sito', 'first' => 'Anna', 'last' => 'De Micco', 'phones' => ['3208225786'], 'callback' => '2026-11-06'],
        ['operator' => 'Lea Pellegrino', 'status' => 'Da Richiamare', 'source' => 'Sito', 'first' => 'Simona', 'last' => 'Feole', 'phones' => ['3205615459'], 'callback' => '2026-11-10'],
        ['operator' => 'Gaetano Della Porta', 'status' => 'Da Richiamare', 'source' => 'Centralino', 'first' => 'Carlo', 'last' => 'Moro', 'phones' => ['3393282918'], 'callback' => '2026-11-09', 'city' => 'Crispano'],
        ['operator' => 'Lea Pellegrino', 'status' => 'Da Richiamare', 'source' => 'Centralino', 'first' => 'Rosaria', 'last' => 'Castaldo', 'phones' => ['3911015728'], 'callback' => '2026-11-10', 'email' => 'castaldorosaria980@gmail.com'],
        ['operator' => 'Simone Seneca', 'status' => 'Da Richiamare', 'source' => 'Sito', 'first' => 'Salvatore', 'last' => 'Morra', 'phones' => ['3505676570'], 'callback' => '2026-11-06'],
        ['operator' => 'Valentina Scala', 'status' => 'Da Richiamare', 'source' => 'Sito', 'first' => 'Raffaella Oriana', 'last' => 'Casola', 'phones' => ['3880970745'], 'callback' => '2026-11-09', 'notes' => self::NO_ANSWER],
        ['operator' => 'Francesca Forgione', 'status' => 'Attesa esito SFL/ADI', 'source' => 'Sito', 'first' => 'Emanuele', 'last' => 'Falcone', 'phones' => ['3513715288'], 'callback' => '2026-11-20'],
        ['operator' => 'Lea Pellegrino', 'status' => 'Da Richiamare', 'source' => 'Sito', 'first' => 'Luigi', 'last' => 'Esposito', 'phones' => ['3338345775'], 'callback' => '2026-11-11', 'email' => 'luigiesposito9873@gmail.com', 'city' => 'Naples'],
    ];
}
