<?php

namespace Database\Seeders\QualificaCatalog;

/**
 * The open "trattative" of Autofinanziato - Campania, from the user's sheet
 * "Autofinanziato Campania_Trattative 06-11-26.xlsx" (user directive 2026-11-07,
 * same shape as AplOrientationRequestCatalogue). Cleaned at the source: names split
 * by hand, phones as Italian digits (the 39 country code dropped), product spelled
 * as the category's product, "Preferenza Orario corso" as the enum value.
 *
 * User decisions 2026-11-07: "Corso Gare d'Appalto - 2026" stays here with no
 * product row and the note GARE_NOTE; a cell with two products keeps the first,
 * the second in the notes (the category allows one row); "operatore agenzia
 * viaggi" stays here with the product in the notes; nicknames completed from the
 * email where it is plain (Ilaria Battista, Matteo Renzullo, Giusy Polise,
 * Federica), the rest kept as written. Empty Fonte -> "No fonte".
 */
final class SelfFundedCampaniaRequestCatalogue
{
    public const string CATEGORY = 'Autofinanziato - Campania';

    /** Reporters missing from the referents: Corsidia is a company. */
    public const array NEW_REPORTERS = ['Corsidia' => null];

    private const string OSS = 'OSS - Operatore Socio Sanitario';

    private const string ASO = 'ASO - Assistente Studio Odontoiatrico';

    private const string ASACOM = "ASACOM - Assistente all'Autonomia e alla Comunicazione";

    private const string OPI = "OPI - Operatore per l'Infanzia";

    private const string OAC = 'OAC - Operatore Contabile Amministrativo';

    private const string OSA = 'OSA - Operatore Socio Assistenziale';

    private const string ABA = 'Tecnico del Comportamento ABA - Online';

    private const string EIPASS = 'EIPASS';

    private const string ASO_UPDATE = 'Aggiornamento ASO';

    private const string GARE_NOTE = "Prodotto: Corso Gare d'Appalto - 2026";

    private const string CALLBACK = 'Da Richiamare';

    private const string NEGOTIATION = 'In trattativa';

    private const string NO_ANSWER = 'Non risponde';

    private const string NEW_CONTACT = 'Nuovo Contatto';

    private const string APPOINTMENT = 'Appuntamento';

    /**
     * One entry per sheet row (2..120). Keys as AplOrientationRequestCatalogue, plus
     * product, time_preference (morning|afternoon) and price.
     *
     * @var list<array<string, mixed>>
     */
    public const array ROWS = [
        ['operator' => 'Marco Fedele', 'status' => self::NEW_CONTACT, 'source' => 'Social', 'first' => 'Erika', 'last' => 'Gravina', 'phones' => ['3479994293'], 'email' => 'gravina.erika@virgilio.it', 'product' => self::OPI],
        ['operator' => 'Vincenzo Crisci', 'status' => self::CALLBACK, 'source' => 'Social', 'first' => 'Mariarosaria', 'last' => 'Costanzo', 'phones' => ['3450134584'], 'email' => 'costanzo.mr87@gmail.com', 'product' => self::OPI],
        ['operator' => 'Vincenzo Crisci', 'status' => self::CALLBACK, 'source' => 'Social', 'first' => 'Valentina', 'last' => 'Chiatto', 'phones' => ['3343614171'], 'product' => self::EIPASS],
        ['operator' => 'Anna Garofalo', 'status' => self::NEGOTIATION, 'source' => 'Social', 'first' => 'Matteo', 'last' => 'Specchio', 'phones' => ['3465792911'], 'callback' => '2026-11-03', 'email' => 'specchiomatteo8@gmail.com', 'product' => self::ASACOM],
        ['operator' => 'Anna Garofalo', 'status' => self::CALLBACK, 'source' => 'Centralino', 'first' => 'Domiziana', 'last' => 'Tommasini', 'phones' => ['3455796820'], 'callback' => '2026-10-19', 'product' => self::ASACOM],
        ['operator' => 'Francesca Forgione', 'status' => self::CALLBACK, 'source' => 'Social', 'first' => 'Mary', 'last' => 'Sbriglio', 'phones' => ['3472835100'], 'callback' => '2026-11-16', 'email' => 'marysbriglio@hotmail.com', 'product' => self::ASACOM],
        ['operator' => 'Valentina Scala', 'status' => self::CALLBACK, 'source' => 'Sito', 'first' => 'Carmela', 'last' => 'Martino', 'phones' => ['3383619347'], 'callback' => '2027-04-07', 'email' => 'martino_carmela@yahoo.it', 'product' => self::ASACOM],
        ['operator' => 'Vincenzo Crisci', 'status' => self::CALLBACK, 'source' => 'Derivanti da CAF e Segnalazioni', 'first' => 'Nadia', 'last' => 'Caponi', 'phones' => ['3938089004'], 'email' => 'caponinadia@gmail.com', 'product' => self::ASACOM, 'reporter' => 'Corsidia'],
        ['operator' => 'Anna Garofalo', 'status' => self::CALLBACK, 'source' => 'Social', 'first' => 'Tiziana', 'last' => 'Pappalardo', 'phones' => ['3402767450'], 'callback' => '2027-01-11', 'email' => 'pappalardomorena28@gmail.com', 'product' => self::ASACOM],
        ['operator' => 'Giulia Costanzo', 'status' => self::NO_ANSWER, 'source' => 'Social', 'first' => 'Michela', 'last' => 'Carrella', 'phones' => ['3928585414'], 'callback' => '2026-11-09', 'email' => 'mimmoranieri@gmail.com', 'product' => self::OPI],
        ['operator' => 'Valentina Scala', 'status' => self::CALLBACK, 'source' => 'Social', 'first' => 'Elisabetta', 'last' => 'Sorrentino', 'phones' => ['3273570643'], 'callback' => '2026-11-25', 'email' => 'elisabetta.sorrentino@gmail.com', 'product' => self::OPI],
        ['operator' => 'Anna Garofalo', 'status' => self::CALLBACK, 'source' => 'Sito', 'first' => 'Simone', 'last' => 'Villano', 'phones' => ['3921934107'], 'callback' => '2026-11-03', 'email' => 'villanosimone@live.it', 'product' => self::OAC],
        ['operator' => 'Francesca Forgione', 'status' => self::CALLBACK, 'source' => 'Passaparola', 'first' => 'Daria', 'last' => 'Fera', 'phones' => ['3400514106'], 'callback' => '2026-11-16', 'email' => 'dasha.fera@gmail.com', 'product' => self::OPI],
        ['operator' => 'Vincenzo Crisci', 'status' => self::CALLBACK, 'source' => 'Social', 'first' => 'Gianluca', 'last' => 'Falivene', 'phones' => ['3287350145'], 'email' => 'gianlucafalivene@gmail.com', 'product' => self::OSA],
        ['operator' => 'Anna Garofalo', 'status' => self::NEGOTIATION, 'source' => 'Social', 'first' => 'Tina', 'last' => 'Perasole', 'phones' => ['3311489088'], 'callback' => '2026-10-26', 'email' => 'maria.perasole.1959@gmail.com', 'product' => self::OSA, 'time_preference' => 'morning', 'notes' => 'Prodotto richiesto anche: ASACOM'],
        ['operator' => 'Anna Garofalo', 'status' => self::NEGOTIATION, 'source' => 'Social', 'first' => 'Rita', 'last' => 'De Vincenzo', 'phones' => ['3791035980'], 'callback' => '2026-10-20', 'email' => 'ritadevincenzo5@gmail.com', 'product' => self::OSS],
        ['operator' => 'Giulia Costanzo', 'status' => self::CALLBACK, 'source' => 'Social', 'first' => 'Imma', 'last' => 'Di Martino', 'phones' => ['3773568023'], 'email' => 'dimartinoimma84@gmail.com', 'product' => self::OPI],
        ['operator' => 'Anna Garofalo', 'status' => self::CALLBACK, 'source' => 'Sito', 'first' => 'Giovannantida', 'last' => 'Messina', 'phones' => ['3388832879'], 'callback' => '2026-10-26', 'product' => self::ASO, 'notes' => 'Detta Vanna. Prodotto richiesto anche: Aggiornamento ASO'],
        ['operator' => 'Anna Garofalo', 'status' => self::CALLBACK, 'source' => 'Social', 'first' => 'Lia', 'last' => 'Angelino', 'phones' => ['3248878167'], 'callback' => '2026-10-20', 'email' => 'angelinoconsiglia@libero.it', 'product' => self::OPI],
        ['operator' => 'Francesca Forgione', 'status' => self::NEW_CONTACT, 'source' => 'Social', 'first' => 'Rosanna', 'last' => 'Avino', 'phones' => ['3389687349'], 'email' => 'rosanna970@live.it', 'product' => self::OPI],
        ['operator' => 'Francesca Forgione', 'status' => self::NEW_CONTACT, 'source' => 'Social', 'first' => 'Ilaria', 'last' => 'Ottaviano', 'phones' => ['3288335219'], 'email' => 'ottavuanoilaria@gmail.com', 'product' => self::OSS],
        ['operator' => 'Anna Garofalo', 'status' => self::CALLBACK, 'source' => 'Social', 'first' => 'Francesca', 'last' => 'Campolongo', 'phones' => ['3517804891'], 'callback' => '2026-10-20', 'notes' => 'Nome account: Stella'],
        ['operator' => 'Giulia Costanzo', 'status' => self::CALLBACK, 'source' => 'Social', 'first' => 'Nicolina', 'last' => 'Iadaresta', 'phones' => ['3474676424'], 'email' => 'nicolinaiadaresta@gmail.com', 'product' => self::OAC],
        ['operator' => 'Anna Garofalo', 'status' => self::CALLBACK, 'source' => 'Social', 'first' => 'Vincenza', 'last' => 'Tumello', 'phones' => ['3209406290'], 'callback' => '2026-10-20', 'email' => 'rinoraimondo75@gmail.com', 'product' => self::OAC],
        ['operator' => 'Anna Garofalo', 'status' => self::CALLBACK, 'source' => 'Sito', 'first' => 'Antonella', 'last' => 'De Vita', 'phones' => ['3792941841'], 'callback' => '2026-10-09', 'product' => self::ASO, 'time_preference' => 'afternoon'],
        ['operator' => 'Valentina Scala', 'status' => self::CALLBACK, 'source' => 'Sito', 'first' => 'Veronica', 'last' => 'Ledda', 'phones' => ['3458594262'], 'callback' => '2026-11-24', 'email' => 'veronicaledda509@gmail.com', 'product' => self::EIPASS],
        ['operator' => 'Valentina Scala', 'status' => self::NEGOTIATION, 'source' => 'Centralino', 'first' => 'Mario', 'last' => 'Iovinella', 'phones' => ['3336732996'], 'callback' => '2026-11-11', 'product' => self::OPI],
        ['operator' => 'Lea Pellegrino', 'status' => self::CALLBACK, 'source' => 'Sito', 'first' => 'Sarah', 'last' => 'Morabito', 'phones' => ['3205618389'], 'callback' => '2026-11-25', 'email' => 'sarahmorabito22@gmail.com', 'product' => self::OAC],
        ['operator' => 'Anna Garofalo', 'status' => self::NEW_CONTACT, 'source' => 'Sito', 'first' => 'Giovanna', 'last' => 'Greco', 'phones' => ['3392855676'], 'email' => 'giovannagreco1977@gmail.com'],
        ['operator' => 'Anna Garofalo', 'status' => self::CALLBACK, 'source' => 'Passaparola', 'first' => 'Giusy', 'last' => 'Perfetto', 'phones' => ['3389000857'], 'callback' => '2026-11-03', 'product' => self::OSS],
        ['operator' => 'Anna Garofalo', 'status' => self::CALLBACK, 'source' => 'Centralino', 'first' => 'Sara', 'last' => 'Severino', 'phones' => ['3665294568'], 'callback' => '2026-10-06'],
        ['operator' => 'Francesca Forgione', 'status' => self::CALLBACK, 'source' => 'Passaparola', 'first' => 'Ida', 'last' => 'Marino', 'phones' => ['3337534071'], 'callback' => '2026-12-01', 'email' => 'manuelida2014@gmail.com', 'product' => self::OSS],
        ['operator' => 'Francesca Forgione', 'status' => self::CALLBACK, 'source' => 'Centralino', 'first' => 'Stefania', 'last' => 'Di Massimo', 'phones' => ['3927970893'], 'callback' => '2026-12-14', 'email' => 'sdmstefaniasdm@gmail.com', 'product' => self::OSA],
        ['operator' => 'Anna Garofalo', 'status' => self::CALLBACK, 'source' => 'Sito', 'first' => 'Cornelia', 'last' => 'Cazacu', 'phones' => ['3881965241'], 'callback' => '2026-11-06', 'product' => self::EIPASS],
        ['operator' => 'Francesca Forgione', 'status' => self::CALLBACK, 'source' => 'Sito', 'first' => 'Ambra', 'last' => 'Coppeta', 'phones' => ['3884942108'], 'callback' => '2027-01-04', 'product' => self::ASO_UPDATE],
        ['operator' => 'Francesca Forgione', 'status' => self::CALLBACK, 'source' => 'Sito', 'first' => 'Natalia', 'last' => 'Rohoza', 'phones' => ['3311833763'], 'callback' => '2027-01-11', 'product' => self::ASO_UPDATE],
        ['operator' => 'Anna Garofalo', 'status' => self::CALLBACK, 'source' => 'Centralino', 'first' => 'Amalia', 'last' => 'Belardo', 'phones' => ['3333729224'], 'callback' => '2026-10-20', 'product' => self::ASACOM],
        ['operator' => 'Francesca Forgione', 'status' => self::CALLBACK, 'source' => 'Sito', 'first' => 'Giovanna', 'last' => 'Rossi', 'phones' => ['3498538924'], 'callback' => '2026-12-14', 'product' => self::OAC],
        ['operator' => 'Anna Garofalo', 'status' => self::CALLBACK, 'source' => 'Centralino', 'first' => 'Teresa', 'last' => 'Sole', 'phones' => ['3349632113'], 'callback' => '2026-10-20', 'product' => self::EIPASS],
        ['operator' => 'Francesca Forgione', 'status' => self::CALLBACK, 'source' => 'Sito', 'first' => 'Nevila', 'last' => 'Hoxha', 'phones' => ['3477003818'], 'callback' => '2026-12-14', 'product' => self::OPI],
        ['operator' => 'Valentina Scala', 'status' => self::CALLBACK, 'source' => 'Sito', 'first' => 'Imma', 'last' => 'Palumbo', 'phones' => ['3466183528'], 'callback' => '2027-01-12', 'product' => self::OSS],
        ['operator' => 'Anna Garofalo', 'status' => self::CALLBACK, 'source' => 'Passaparola', 'first' => 'Simona', 'last' => 'Moccia', 'phones' => ['3389362970'], 'callback' => '2027-01-11', 'product' => self::ABA],
        ['operator' => 'Anna Garofalo', 'status' => self::CALLBACK, 'source' => 'Sito', 'first' => 'Carlotta', 'last' => 'Tami', 'phones' => ['3494518853'], 'callback' => '2026-10-20', 'email' => 'tamicarlotta@gmail.com', 'product' => self::OPI],
        ['operator' => 'Anna Garofalo', 'status' => self::CALLBACK, 'source' => 'Social', 'first' => 'Elisa', 'last' => 'Riccitiello', 'phones' => ['3295633350'], 'callback' => '2026-11-04', 'email' => 'elisariccitiello@yahoo.com', 'product' => self::OPI],
        ['operator' => 'Giulia Costanzo', 'status' => self::CALLBACK, 'source' => 'Social', 'first' => 'Tonia', 'last' => 'Delia', 'phones' => ['3283281165'], 'email' => 'antoniettasgambato81@gmail.com', 'product' => self::OPI],
        ['operator' => 'Anna Garofalo', 'status' => self::CALLBACK, 'source' => 'Passaparola', 'first' => 'Lucia', 'last' => 'Vergara', 'phones' => ['3318711254'], 'callback' => '2027-07-19', 'product' => self::ASACOM],
        ['operator' => 'Giulia Costanzo', 'status' => self::CALLBACK, 'source' => 'Passaparola', 'first' => 'Michela', 'last' => 'Finizio', 'phones' => ['3756407495'], 'product' => self::ASO],
        ['operator' => 'Giulia Costanzo', 'status' => self::CALLBACK, 'source' => 'Social', 'first' => 'Angela', 'last' => 'Pagano', 'phones' => ['3516816339'], 'callback' => '2027-01-04', 'email' => 'pgnangelo@gmail.com', 'product' => self::OPI],
        ['operator' => 'Giulia Costanzo', 'status' => self::NO_ANSWER, 'source' => 'Social', 'first' => 'Ilaria', 'last' => 'Battista', 'phones' => ['3934038850'], 'callback' => '2026-11-09', 'email' => 'ilaria.battista-09@hotmail.com', 'product' => self::OAC],
        ['operator' => 'Francesca Forgione', 'status' => self::CALLBACK, 'source' => 'Social', 'first' => 'Giusy', 'last' => 'Marino', 'phones' => ['3383789098'], 'callback' => '2026-12-14', 'email' => 'giusy.87@hotmail.it', 'product' => self::OSA],
        ['operator' => 'Simone Seneca', 'status' => self::CALLBACK, 'source' => 'Passaparola', 'first' => 'Anna', 'last' => 'Cataldo', 'phones' => ['3925630640'], 'callback' => '2027-01-07', 'email' => 'ariannacataldo@hotmail.com', 'product' => self::OSA],
        ['operator' => 'Giulia Costanzo', 'status' => self::NO_ANSWER, 'source' => 'Sito', 'first' => 'Massimo', 'last' => 'Berto', 'phones' => ['3389237283'], 'callback' => '2026-11-09', 'email' => 'massimoberto68@gmail.com'],
        ['operator' => 'Anna Garofalo', 'status' => self::NEGOTIATION, 'source' => 'Passaparola', 'first' => 'Alessia', 'last' => 'Bariselli', 'phones' => ['3381819403'], 'callback' => '2026-10-27', 'email' => 'alessiabariselli75@gmail.com', 'product' => self::ASACOM],
        ['operator' => 'Anna Garofalo', 'status' => self::NEW_CONTACT, 'source' => 'Centralino', 'first' => 'Giusy', 'last' => 'Rese', 'phones' => ['3296698502'], 'product' => self::OAC],
        ['operator' => 'Giulia Costanzo', 'status' => self::CALLBACK, 'source' => 'Social', 'first' => 'Giovanna', 'last' => 'Annunziata', 'phones' => ['3886510330'], 'callback' => '2026-12-15', 'email' => 'vanna.vanna1976@gmail.com', 'product' => self::OAC],
        ['operator' => 'Francesca Forgione', 'status' => self::CALLBACK, 'source' => 'Social', 'first' => 'Vita', 'last' => 'Fomenko', 'phones' => ['3277757127'], 'callback' => '2026-11-23', 'email' => 'vitavashchuk2019@gmail.com', 'product' => self::OAC],
        ['operator' => 'Francesca Forgione', 'status' => self::CALLBACK, 'source' => 'Social', 'first' => 'Maria Grazia', 'last' => 'Catuogno', 'phones' => ['3426167983'], 'callback' => '2027-01-07', 'product' => self::OPI],
        ['operator' => 'Anna Garofalo', 'status' => self::CALLBACK, 'source' => 'Social', 'first' => 'Mara', 'last' => 'Pettorusso', 'phones' => ['3440432767'], 'callback' => '2027-01-10', 'email' => 'maralabella2001@gmail.com', 'product' => self::OPI],
        ['operator' => 'Anna Garofalo', 'status' => self::NEGOTIATION, 'source' => 'Social', 'first' => 'Rossella', 'last' => "D'Angelo", 'phones' => ['3881241858'], 'callback' => '2026-12-09', 'email' => 'rossellinadangelo@icloud.com', 'product' => self::OPI],
        ['operator' => 'Anna Garofalo', 'status' => self::CALLBACK, 'source' => 'Sito', 'first' => 'Ilaria', 'last' => 'Lobozzo', 'phones' => ['3319636999'], 'callback' => '2026-10-22', 'email' => 'ilarialobozzo99@gmail.com', 'product' => self::OPI],
        ['operator' => 'Vincenzo Crisci', 'status' => self::CALLBACK, 'source' => 'Social', 'first' => 'Monica', 'last' => 'Di Domenico', 'phones' => ['3471264333'], 'callback' => '2026-11-06', 'email' => 'monicadidomenico1975@gmail.com', 'product' => self::OAC],
        ['operator' => 'Giulia Costanzo', 'status' => self::CALLBACK, 'source' => 'Social', 'first' => 'Mariagiovanna', 'last' => 'Tondolo', 'phones' => ['3343983592'], 'email' => 'mariagiovannatondolo80@gmail.com', 'product' => self::OAC],
        ['operator' => 'Vincenzo Crisci', 'status' => self::CALLBACK, 'source' => 'Social', 'first' => 'Vic', 'last' => null, 'phones' => ['3921190197'], 'callback' => '2026-11-03', 'email' => 'vickalagowski05@gmail.com', 'product' => self::OPI],
        ['operator' => 'Anna Garofalo', 'status' => self::CALLBACK, 'source' => 'Social', 'first' => 'Franca', 'last' => 'Della Camera', 'phones' => ['3388879944'], 'callback' => '2026-11-04', 'email' => 'francadellacamera@yahoo.it', 'product' => self::OAC],
        ['operator' => 'Vincenzo Crisci', 'status' => self::CALLBACK, 'source' => 'Social', 'first' => 'Mauro', 'last' => 'Carlino', 'phones' => ['3476130203'], 'callback' => '2026-11-03', 'email' => 'carlinomauro@gmail.com', 'product' => self::OAC],
        ['operator' => 'Francesca Forgione', 'status' => self::CALLBACK, 'source' => 'Social', 'first' => 'Maria Rosaria', 'last' => 'Cinque', 'phones' => ['3662414017'], 'callback' => '2026-11-16', 'email' => 'santonastaso88@gmail.com', 'product' => self::OAC],
        ['operator' => 'Lidia Russo', 'status' => self::CALLBACK, 'source' => 'Social', 'first' => 'Giuseppe', 'last' => 'Crisafulli', 'phones' => ['3471099607'], 'callback' => '2026-11-06', 'email' => 'crisafulligroupsrl@libero.it', 'notes' => self::GARE_NOTE],
        ['operator' => 'Lidia Russo', 'status' => self::CALLBACK, 'source' => 'Social', 'first' => 'Giuseppe', 'last' => 'Cangemi', 'phones' => ['3382186936'], 'callback' => '2026-11-06', 'email' => 'infoprogettocantiere@gmail.com', 'notes' => self::GARE_NOTE],
        ['operator' => 'Lidia Russo', 'status' => self::APPOINTMENT, 'source' => 'Social', 'first' => 'Francesco', 'last' => 'Chianese', 'phones' => ['3511864869'], 'callback' => '2026-10-30', 'email' => 'francescochianese676@gmail.com', 'notes' => self::GARE_NOTE],
        ['operator' => 'Lidia Russo', 'status' => self::CALLBACK, 'source' => 'Social', 'first' => 'Adim', 'last' => 'Mohammed', 'phones' => ['3202343254'], 'callback' => '2026-11-06', 'email' => 'swedmom8@gmail.com', 'notes' => self::GARE_NOTE],
        ['operator' => 'Valentina Scala', 'status' => self::NEGOTIATION, 'source' => 'Social', 'first' => 'Immacolata', 'last' => 'De Luca', 'phones' => ['3287823735'], 'callback' => '2026-11-11', 'product' => self::EIPASS],
        ['operator' => 'Lidia Russo', 'status' => self::CALLBACK, 'source' => 'Social', 'first' => 'Alberto', 'last' => 'Ragazzo', 'phones' => ['3402492691'], 'callback' => '2026-10-30', 'email' => 'duke.posta@libero.it', 'notes' => self::GARE_NOTE],
        ['operator' => 'Lidia Russo', 'status' => self::CALLBACK, 'source' => 'Social', 'first' => 'Dona', 'last' => null, 'phones' => ['3312818100'], 'callback' => '2026-10-30', 'email' => 'd.depascale8@studenti.unisa.it', 'notes' => self::GARE_NOTE],
        ['operator' => 'Lidia Russo', 'status' => self::CALLBACK, 'source' => 'Social', 'first' => 'Luciana', 'last' => 'Tolmino', 'phones' => ['3510518302'], 'callback' => '2026-10-30', 'email' => 'lucianatolmino@gmail.com', 'notes' => self::GARE_NOTE],
        ['operator' => 'Lidia Russo', 'status' => self::CALLBACK, 'source' => 'Social', 'first' => 'Lucia', 'last' => 'Caiazza', 'phones' => ['3298515178'], 'callback' => '2026-10-30', 'email' => 'lucia.caiazza92@gmail.com', 'notes' => self::GARE_NOTE],
        ['operator' => 'Francesca Forgione', 'status' => self::CALLBACK, 'source' => 'Social', 'first' => 'Valeria', 'last' => 'Valentino', 'phones' => ['3385493694'], 'callback' => '2026-11-09', 'email' => 'frufri92@hotmail.it', 'product' => self::OAC],
        ['operator' => 'Giulia Costanzo', 'status' => self::CALLBACK, 'source' => 'Social', 'first' => 'Anna', 'last' => 'Cinque', 'phones' => ['3333390298'], 'callback' => '2026-11-06', 'email' => 'annacinque79@hotmail.com', 'product' => self::OAC],
        ['operator' => 'Francesca Forgione', 'status' => self::CALLBACK, 'source' => 'Sito', 'first' => 'Ida Fabiana', 'last' => 'Giugliano', 'phones' => ['3513406510'], 'callback' => '2026-11-23', 'email' => 'idafabianagiugliano00@gmail.com', 'product' => self::ASO],
        ['operator' => 'Lidia Russo', 'status' => self::CALLBACK, 'source' => 'Social', 'first' => 'Sara', 'last' => 'Romano', 'phones' => ['3473920019'], 'callback' => '2026-10-30', 'email' => 'peonia2007@virgilio.it', 'notes' => self::GARE_NOTE],
        ['operator' => 'Lidia Russo', 'status' => self::CALLBACK, 'source' => 'Social', 'first' => 'Luigi Cristoforo', 'last' => 'Di Puorto', 'phones' => ['3345739271'], 'callback' => '2026-10-30', 'email' => 'luigicristoforodipuorto@gmail.com', 'notes' => self::GARE_NOTE],
        ['operator' => 'Lidia Russo', 'status' => self::CALLBACK, 'source' => 'Social', 'first' => 'Matteo', 'last' => 'Renzullo', 'phones' => ['3791561275'], 'callback' => '2026-10-30', 'email' => 'matteo.renzullo@gmail.com', 'notes' => self::GARE_NOTE],
        ['operator' => 'Lidia Russo', 'status' => self::CALLBACK, 'source' => 'Social', 'first' => 'Anna', 'last' => 'Orsi', 'phones' => ['3398807158'], 'callback' => '2026-10-30', 'email' => 'orsia2023@gmail.com', 'notes' => self::GARE_NOTE],
        ['operator' => 'Lidia Russo', 'status' => self::CALLBACK, 'source' => 'Social', 'first' => 'Marco', 'last' => 'Petrelli', 'phones' => ['3357327490'], 'callback' => '2026-10-30', 'email' => 'info@idrospurgo.it', 'notes' => self::GARE_NOTE],
        ['operator' => 'Giulia Costanzo', 'status' => self::NO_ANSWER, 'source' => 'Social', 'first' => 'Valentina', 'last' => 'Pannuti', 'phones' => ['3297411548'], 'callback' => '2026-12-04', 'email' => 'valentinapannuti@gmail.com', 'product' => self::OAC],
        ['operator' => 'Anna Garofalo', 'status' => self::APPOINTMENT, 'source' => 'No fonte', 'first' => 'Mariangela', 'last' => 'Esposito', 'phones' => ['3792350640'], 'callback' => '2026-11-06', 'product' => self::OSS],
        ['operator' => 'Lidia Russo', 'status' => self::CALLBACK, 'source' => 'Social', 'first' => 'Federica', 'last' => null, 'phones' => ['3488624607'], 'callback' => '2026-10-30', 'email' => 'lafederica92@hotmail.it', 'notes' => self::GARE_NOTE],
        ['operator' => 'Giulia Costanzo', 'status' => self::NO_ANSWER, 'source' => 'Social', 'first' => 'Alessandra M.', 'last' => 'Montanaro', 'phones' => ['3662501929'], 'callback' => '2026-11-09', 'email' => 'alessandra_m96@protonmail.com', 'product' => self::OAC],
        ['operator' => 'Anna Garofalo', 'status' => self::CALLBACK, 'source' => 'Social', 'first' => 'Marika', 'last' => 'Cascino', 'phones' => ['3898965551'], 'callback' => '2026-11-05', 'email' => 'marikacascino03@gmail.com', 'product' => self::OSS],
        ['operator' => 'Lidia Russo', 'status' => self::CALLBACK, 'source' => 'Sito', 'first' => 'Valeria', 'last' => 'Romani', 'phones' => ['3452119417'], 'callback' => '2026-10-30', 'email' => 'v.romani@evin.it', 'notes' => self::GARE_NOTE],
        ['operator' => 'Anna Garofalo', 'status' => self::APPOINTMENT, 'source' => 'Centralino', 'first' => 'Enzo', 'last' => 'Perrotta', 'phones' => ['3398714375'], 'callback' => '2026-11-06', 'product' => self::OSA],
        ['operator' => 'Giulia Costanzo', 'status' => self::CALLBACK, 'source' => 'Social', 'first' => 'Antonio', 'last' => "D'Angelo", 'phones' => ['3271741489'], 'callback' => '2026-11-09', 'product' => self::OAC],
        ['operator' => 'Lidia Russo', 'status' => self::CALLBACK, 'source' => 'Social', 'first' => 'Alessandro', 'last' => 'Faraone', 'phones' => ['3896241041'], 'callback' => '2026-11-03', 'email' => 'ale.faraone87@gmail.com', 'notes' => self::GARE_NOTE],
        ['operator' => 'Lidia Russo', 'status' => self::CALLBACK, 'source' => 'Social', 'first' => 'Antonio', 'last' => 'Natale', 'phones' => ['3880000000'], 'callback' => '2026-11-03', 'email' => 'antonio.natale95.an@gmail.com', 'notes' => self::GARE_NOTE],
        ['operator' => 'Anna Garofalo', 'status' => self::NEW_CONTACT, 'source' => 'Social', 'first' => 'Angela', 'last' => 'Addati', 'phones' => ['3891874844'], 'callback' => '2026-11-02', 'email' => 'angela.addati89@gmail.com'],
        ['operator' => 'Giulia Costanzo', 'status' => self::CALLBACK, 'source' => 'Social', 'first' => 'Anna', 'last' => 'Doroteo', 'phones' => ['3881097162'], 'email' => 'anna88doroteo@gmail.com', 'product' => self::OPI],
        ['operator' => 'Francesca Forgione', 'status' => self::CALLBACK, 'source' => 'Centralino', 'first' => 'Carmen', 'last' => 'Natalie', 'phones' => ['3333662420'], 'callback' => '2026-11-16', 'product' => self::OPI],
        ['operator' => 'Lidia Russo', 'status' => self::CALLBACK, 'source' => 'Social', 'first' => 'Alessandro', 'last' => 'Zanu', 'phones' => ['3701605831'], 'callback' => '2026-11-03', 'email' => 'alessandrozanusso@yahoo.it', 'notes' => self::GARE_NOTE],
        ['operator' => 'Anna Garofalo', 'status' => self::NEGOTIATION, 'source' => 'Passaparola', 'first' => 'Vincenzo', 'last' => 'Sforza', 'phones' => ['3311214931'], 'callback' => '2026-11-04', 'email' => 'vincenzosforza2002@icloud.com', 'product' => self::OSS],
        ['operator' => 'Anna Garofalo', 'status' => self::CALLBACK, 'source' => 'Social', 'first' => 'Angela', 'last' => "Dell'Aversana", 'phones' => ['3483717091'], 'callback' => '2026-11-09', 'email' => 'dellaversanaangela@libero.it'],
        ['operator' => 'Giulia Costanzo', 'status' => self::NO_ANSWER, 'source' => 'Social', 'first' => 'Giusy', 'last' => 'Polise', 'phones' => ['3331004930'], 'callback' => '2026-11-09', 'email' => 'polisegiusy@gmail.com'],
        ['operator' => 'Valentina Scala', 'status' => self::CALLBACK, 'source' => 'Social', 'first' => 'Francesca', 'last' => 'Simioni', 'phones' => ['3924532648'], 'callback' => '2026-11-12', 'email' => 'simionifrancesca8@gmail.com', 'product' => self::OAC],
        ['operator' => 'Francesca Forgione', 'status' => self::CALLBACK, 'source' => 'Social', 'first' => 'Francesca', 'last' => null, 'phones' => ['3338260599'], 'callback' => '2026-11-09', 'product' => self::ASACOM, 'notes' => '15.30'],
        ['operator' => 'Anna Garofalo', 'status' => self::NEGOTIATION, 'source' => 'Sito', 'first' => 'Eleonora', 'last' => null, 'phones' => ['3461312038'], 'callback' => '2026-11-10', 'product' => self::OAC],
        ['operator' => 'Simone Seneca', 'status' => self::CALLBACK, 'source' => 'Social', 'first' => 'Giuseppe', 'last' => 'Napoletano', 'phones' => ['3386413779'], 'callback' => '2026-11-09', 'email' => 'gnapoletano730@gmail.com', 'product' => self::OAC],
        ['operator' => 'Lea Pellegrino', 'status' => self::CALLBACK, 'source' => 'Centralino', 'first' => 'Emanuele', 'last' => 'Izzo', 'phones' => ['3333878218'], 'callback' => '2026-11-10', 'product' => self::OSS],
        ['operator' => 'Anna Garofalo', 'status' => self::CALLBACK, 'source' => 'Passaparola', 'first' => 'Anna', 'last' => 'Magliulo', 'phones' => ['3398429972'], 'callback' => '2026-11-09', 'product' => self::ASO],
        ['operator' => 'Giulia Costanzo', 'status' => self::NO_ANSWER, 'source' => 'Social', 'first' => 'Alessia', 'last' => 'Romanetti', 'phones' => ['3927183607'], 'callback' => '2026-11-09', 'email' => 'alessiaromanetti@gmail.com', 'product' => self::OAC],
        ['operator' => 'Anna Garofalo', 'status' => self::NEGOTIATION, 'source' => 'Sito', 'first' => 'Iolanda', 'last' => 'Santangelo', 'phones' => ['3427416563'], 'callback' => '2026-11-09', 'product' => self::OAC],
        ['operator' => 'Gaetano Della Porta', 'status' => self::CALLBACK, 'source' => 'Sito', 'first' => 'Iolanda', 'last' => 'Vivolo', 'phones' => ['3662477603'], 'callback' => '2026-11-09', 'email' => 'iolandavivolo2003@icloud.com', 'product' => self::EIPASS],
        ['operator' => 'Giulia Costanzo', 'status' => self::CALLBACK, 'source' => 'Social', 'first' => 'Maria', 'last' => 'Dello Margio', 'phones' => ['3406684450'], 'callback' => '2026-11-10', 'email' => 'mariadellomargio9@gmail.com', 'product' => self::ASACOM],
        ['operator' => 'Valentina Scala', 'status' => self::CALLBACK, 'source' => 'Social', 'first' => 'Peppe', 'last' => 'Smimmo', 'phones' => ['3349180082'], 'callback' => '2026-11-13', 'email' => 'giuseppe.smimmo@virgilio.it', 'product' => self::ASACOM],
        ['operator' => 'Gaetano Della Porta', 'status' => self::CALLBACK, 'source' => 'Social', 'first' => 'Fatima', 'last' => 'Ait Bouih', 'phones' => ['3290049716'], 'callback' => '2026-11-09', 'email' => 'fatimaaitbouih56@gmail.com', 'product' => self::OAC],
        ['operator' => 'Francesca Forgione', 'status' => self::CALLBACK, 'source' => 'Social', 'first' => 'Antonella', 'last' => 'Bisceglia', 'phones' => ['3406765219'], 'callback' => '2026-11-09', 'email' => 'antonellabisc22@gmail.com', 'product' => self::OAC],
        ['operator' => 'Anna Garofalo', 'status' => self::NEGOTIATION, 'source' => 'Passaparola', 'first' => 'Francesco', 'last' => 'Palmiero', 'phones' => ['3489524461'], 'callback' => '2026-11-09', 'product' => self::ASACOM],
        ['operator' => 'Lea Pellegrino', 'status' => self::NEGOTIATION, 'source' => 'Centralino', 'first' => 'Sara', 'last' => 'Verde', 'phones' => ['3206427992'], 'callback' => '2026-11-10', 'email' => 'saraverde43@gmail.com', 'product' => self::OSS, 'time_preference' => 'afternoon', 'price' => 1900.0],
        ['operator' => 'Vincenzo Crisci', 'status' => self::CALLBACK, 'source' => 'No fonte', 'first' => 'Anna', 'last' => 'Volpicelli', 'phones' => ['3921763552']],
        ['operator' => 'Francesca Forgione', 'status' => self::NEW_CONTACT, 'source' => 'Sito', 'first' => 'Alessandro', 'last' => 'Romano', 'phones' => ['3389732604'], 'email' => 'alessandroromano0306@gmail.com', 'notes' => 'Prodotto: operatore agenzia viaggi'],
        ['operator' => 'Anna Garofalo', 'status' => self::NEW_CONTACT, 'source' => 'Sito', 'first' => 'Alessandra', 'last' => 'Farì', 'phones' => ['3801430205'], 'email' => 'alessandrafari89@gmail.com', 'product' => self::EIPASS],
        ['operator' => 'Giulia Costanzo', 'status' => self::CALLBACK, 'source' => 'Sito', 'first' => 'Carla', 'last' => 'Cestari', 'phones' => ['3510496994'], 'callback' => '2026-11-11', 'product' => self::ASACOM],
    ];
}
