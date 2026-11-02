<?php

namespace Database\Seeders\QualificaCatalog;

/**
 * The client's reporters ("Segnalatori 2026", user directive 2026-10-30) and
 * their commission percentage per category. Pure data, read by
 * QualificaReporterCommissionSeeder.
 *
 * A reporter already in the legacy system is identified by its legacy id
 * (`old_id`); the others carry the anagraphic needed to create them, the name taken from the NOME
 * column (user directive 2026-10-30): a company there becomes a company card,
 * its contact person going in the note. `row` is
 * the source spreadsheet row, kept for traceability. Corrections agreed with
 * the user: row 7 GDPR 0.01 read as 15%, row 21 FNC "20" read as 20%; row 53
 * (duplicate of row 78, Francesco Lagana') dropped.
 */
final class ReporterCommissionCatalogue
{
    public const string REFERENT_TYPE = 'Segnalatore';

    public const string VALID_FROM = '2026-10-30';

    public const string RULE_NAME_PREFIX = 'Segnalatore';

    /** The "UNI PDR 125 e SA 8000" column: 10% on the first year, 5% on maintenance. */
    public const string MAINTENANCE_NOTE = 'UNI PDR 125 e SA 8000: 10%, mantenimento 5%.';

    /**
     * The spreadsheet columns, each mapped to the categories its rate applies to.
     *
     * @var array<string, list<string>>
     */
    public const array CATEGORIES = [
        'iso' => ['ISO'],
        'soa' => ['SOA'],
        'sic' => ['Sicurezza'],
        'gare' => ['Gare'],
        'gdpr' => ['GDPR'],
        'fnc' => ['Finanza Agevolata - FNC'],
        'pdr' => ['ISO - UNI PDR 125', 'ISO - SA8000'],
    ];

    /**
     * @var list<array{row: int, old_id?: int, first_name?: string, last_name?: string, company_name?: string, email?: string, phone?: string, rates: array<string, string>, note?: string}>
     */
    public const array REPORTERS = [
        ['row' => 2, 'old_id' => 16152, 'rates' => ['iso' => '20', 'soa' => '20', 'sic' => '20', 'gdpr' => '20', 'fnc' => '20', 'pdr' => '10']],
        ['row' => 3, 'old_id' => 17552, 'rates' => ['iso' => '20', 'soa' => '20', 'sic' => '20', 'gdpr' => '20', 'fnc' => '20', 'pdr' => '10'], 'note' => '10% sul mantenimento ISO SOA E GDPR - FNC 20%-10% - APL 10%'],
        ['row' => 4, 'old_id' => 12893, 'rates' => ['soa' => '20', 'sic' => '20', 'gdpr' => '20', 'fnc' => '20', 'pdr' => '10'], 'note' => 'MANTENIMENTO SOA ISO E GDPR 10% - FNC 20% -10% - FINANZA AGEVOLATA 15% - APL 10%'],
        ['row' => 5, 'company_name' => 'Artica Ware Srl', 'email' => 'stefanodari@hotmail.com', 'phone' => '349 471 3811', 'rates' => ['iso' => '10', 'soa' => '10', 'sic' => '10', 'gdpr' => '10', 'pdr' => '10'], 'note' => 'Referente: Stefano Dari. 10% sul mantenimento'],
        ['row' => 6, 'old_id' => 17183, 'rates' => ['iso' => '20', 'soa' => '20', 'sic' => '20', 'gdpr' => '20', 'fnc' => '20', 'pdr' => '10']],
        ['row' => 7, 'old_id' => 17217, 'rates' => ['iso' => '15', 'soa' => '15', 'sic' => '15', 'gdpr' => '15', 'fnc' => '20', 'pdr' => '10']],
        ['row' => 8, 'old_id' => 8959, 'rates' => ['iso' => '20', 'soa' => '20', 'sic' => '20', 'gdpr' => '20', 'fnc' => '20', 'pdr' => '10'], 'note' => '10% sul mantenimento; FNC 20%'],
        ['row' => 9, 'old_id' => 16368, 'rates' => ['iso' => '15', 'soa' => '15', 'sic' => '15', 'gdpr' => '15', 'fnc' => '20', 'pdr' => '10']],
        ['row' => 10, 'old_id' => 17061, 'rates' => ['iso' => '15', 'soa' => '15', 'sic' => '15', 'gdpr' => '15', 'fnc' => '20', 'pdr' => '10']],
        ['row' => 11, 'first_name' => 'Claudio', 'last_name' => 'Falco', 'phone' => '389 217 1299', 'rates' => ['iso' => '15', 'soa' => '15', 'sic' => '15', 'gdpr' => '15', 'pdr' => '10'], 'note' => '10% sul mantenimento'],
        ['row' => 12, 'old_id' => 16790, 'rates' => ['iso' => '15', 'soa' => '15', 'sic' => '15', 'gdpr' => '15', 'fnc' => '20', 'pdr' => '10'], 'note' => 'MANTENIMENTO ISO SOA E GDPR 10% - FNC 20%+10% - APL 10%'],
        ['row' => 13, 'old_id' => 16185, 'rates' => ['iso' => '15', 'soa' => '15', 'sic' => '15', 'gdpr' => '15', 'fnc' => '20', 'pdr' => '10'], 'note' => '10% sul mantenimento'],
        ['row' => 14, 'old_id' => 17511, 'rates' => ['iso' => '15', 'soa' => '15', 'sic' => '15', 'gdpr' => '15', 'fnc' => '20', 'pdr' => '10']],
        ['row' => 15, 'old_id' => 16355, 'rates' => ['iso' => '20', 'soa' => '20', 'sic' => '20', 'gdpr' => '20', 'fnc' => '20', 'pdr' => '10']],
        ['row' => 16, 'old_id' => 8832, 'rates' => ['iso' => '10', 'soa' => '10', 'sic' => '10', 'gdpr' => '10', 'fnc' => '20', 'pdr' => '10']],
        ['row' => 17, 'company_name' => 'Dottor Tech', 'email' => 'dottortecaq@gmail.com', 'phone' => '3487052720', 'rates' => ['iso' => '10', 'soa' => '10', 'sic' => '10', 'gdpr' => '10', 'fnc' => '20', 'pdr' => '10'], 'note' => 'Referente: Daniele Terrano. 5% sul mantenimento'],
        ['row' => 18, 'old_id' => 7850, 'rates' => ['iso' => '20', 'soa' => '20', 'sic' => '20', 'gdpr' => '20', 'fnc' => '20', 'pdr' => '10'], 'note' => '10% sul mantenimento'],
        ['row' => 19, 'old_id' => 16583, 'rates' => ['iso' => '10', 'soa' => '10', 'sic' => '10', 'gdpr' => '10', 'fnc' => '10', 'pdr' => '10'], 'note' => '5% mantenimento'],
        ['row' => 20, 'old_id' => 17413, 'rates' => ['iso' => '15', 'soa' => '15', 'sic' => '15', 'gdpr' => '15', 'fnc' => '20', 'pdr' => '10'], 'note' => '10% sul mantenimento'],
        ['row' => 21, 'first_name' => 'Fabio', 'last_name' => 'Amato', 'email' => 'info@cdlamato.com', 'phone' => '366 4815604', 'rates' => ['iso' => '10', 'soa' => '10', 'sic' => '10', 'gdpr' => '10', 'fnc' => '20', 'pdr' => '10'], 'note' => '5% Sul mantenimento'],
        ['row' => 22, 'old_id' => 17097, 'rates' => ['iso' => '20', 'soa' => '20', 'sic' => '20', 'gdpr' => '20', 'fnc' => '20', 'pdr' => '10']],
        ['row' => 23, 'old_id' => 14411, 'rates' => ['iso' => '20', 'soa' => '20', 'sic' => '20', 'gdpr' => '20', 'fnc' => '20', 'pdr' => '10']],
        ['row' => 24, 'old_id' => 16481, 'rates' => ['iso' => '20', 'soa' => '20', 'sic' => '20', 'gdpr' => '20', 'fnc' => '20', 'pdr' => '10'], 'note' => 'MANTENIMENTO ISO SOA E GDPR 10% - FNC 20%-10% APL 10%'],
        ['row' => 25, 'old_id' => 17597, 'rates' => ['iso' => '10', 'soa' => '10', 'sic' => '10', 'gdpr' => '10', 'fnc' => '20', 'pdr' => '10']],
        ['row' => 26, 'old_id' => 7323, 'rates' => ['iso' => '20', 'soa' => '20', 'sic' => '20', 'gdpr' => '20', 'fnc' => '20', 'pdr' => '10'], 'note' => 'Finanza Agevolata 15% - Apl 10% - FNC 20%-10% // Per le pratiche in essere prima della sottoscrizione nel nuovo accordo resta il mantenimento al 30%. - Successivo a Marzo 2025 Mantenimenti ISO SOA E GDPR 10%-'],
        ['row' => 27, 'old_id' => 16797, 'rates' => ['iso' => '15', 'soa' => '15', 'sic' => '15', 'gdpr' => '15', 'fnc' => '20', 'pdr' => '10']],
        ['row' => 28, 'old_id' => 7231, 'rates' => ['iso' => '20', 'soa' => '20', 'sic' => '20', 'gdpr' => '20', 'fnc' => '20', 'pdr' => '10'], 'note' => 'MANTENIMENTO ISO SOA E GDPR 10% FNC 20-10% APL 10%'],
        ['row' => 29, 'old_id' => 16872, 'rates' => ['iso' => '15', 'soa' => '15', 'sic' => '15', 'gdpr' => '15', 'fnc' => '20', 'pdr' => '10'], 'note' => 'mantenimento 10%'],
        ['row' => 30, 'old_id' => 16922, 'rates' => ['iso' => '15', 'soa' => '15', 'sic' => '15', 'gdpr' => '15', 'fnc' => '20', 'pdr' => '10']],
        ['row' => 31, 'old_id' => 17153, 'rates' => ['iso' => '15', 'soa' => '15', 'sic' => '15', 'gdpr' => '15', 'fnc' => '20', 'pdr' => '10'], 'note' => 'INTESTATO A LETIZIA COSTANTINO'],
        ['row' => 32, 'first_name' => 'Giuseppe', 'last_name' => 'Lista', 'email' => 'lgcafaversa@gmail.com', 'phone' => '376 227 1328', 'rates' => ['iso' => '15', 'soa' => '15', 'sic' => '15', 'gdpr' => '15', 'fnc' => '20', 'pdr' => '10'], 'note' => '10% Mantenimento'],
        ['row' => 33, 'old_id' => 13630, 'rates' => ['iso' => '15', 'soa' => '15', 'sic' => '15', 'gdpr' => '15', 'fnc' => '20', 'pdr' => '10'], 'note' => '10% Mantenimento -  Contatto di Luigi Cirillo'],
        ['row' => 34, 'old_id' => 16533, 'rates' => ['iso' => '10', 'soa' => '10', 'sic' => '10', 'gdpr' => '10', 'pdr' => '10'], 'note' => '5% sul mantenimento'],
        ['row' => 35, 'old_id' => 18650, 'rates' => ['iso' => '20', 'soa' => '20', 'sic' => '20', 'gdpr' => '20', 'fnc' => '20', 'pdr' => '10'], 'note' => 'è stato presentato da Michele'],
        ['row' => 36, 'first_name' => 'Marco', 'last_name' => 'Direttore', 'email' => 'direttoremarco@gmail.com', 'phone' => '346 627 4580', 'rates' => [], 'note' => 'prende solo la % su bando fondi scuola pari al 15%'],
        ['row' => 37, 'old_id' => 8745, 'rates' => ['iso' => '20', 'soa' => '20', 'sic' => '20', 'gdpr' => '20', 'fnc' => '20', 'pdr' => '10'], 'note' => 'Per le pratiche in essere prima della sottoscrizione nel nuovo accordo resta il mantenimento al 20%.'],
        ['row' => 38, 'old_id' => 17303, 'rates' => ['iso' => '15', 'soa' => '15', 'sic' => '15', 'gdpr' => '15', 'fnc' => '20', 'pdr' => '10'], 'note' => 'MANTENIMENTO ISO SOA GDPR 10% - FNC 20%-10% - APL 10%'],
        ['row' => 39, 'old_id' => 18725, 'rates' => ['iso' => '15', 'soa' => '15', 'sic' => '15', 'gdpr' => '15', 'fnc' => '20', 'pdr' => '10']],
        ['row' => 40, 'old_id' => 16081, 'rates' => ['iso' => '20', 'soa' => '20', 'sic' => '20', 'gdpr' => '20', 'fnc' => '20', 'pdr' => '10'], 'note' => 'SUL MANTENIMENTO ISO-SOA 10% GDPR 15%'],
        ['row' => 41, 'old_id' => 17391, 'rates' => ['iso' => '15', 'soa' => '15', 'sic' => '15', 'gdpr' => '20', 'fnc' => '20', 'pdr' => '10'], 'note' => '10% sul mantenimento'],
        ['row' => 42, 'old_id' => 17074, 'rates' => ['iso' => '15', 'soa' => '15', 'sic' => '15', 'gdpr' => '15', 'fnc' => '20', 'pdr' => '10']],
        ['row' => 43, 'old_id' => 13747, 'rates' => ['iso' => '20', 'soa' => '20', 'sic' => '20', 'gdpr' => '20', 'fnc' => '20', 'pdr' => '10']],
        ['row' => 44, 'first_name' => 'Pasquale', 'last_name' => 'Ristorato', 'email' => 'studiodott.ristorato@libero.it', 'phone' => '392 151 6483', 'rates' => ['iso' => '10', 'soa' => '10', 'sic' => '10', 'gdpr' => '10', 'fnc' => '20', 'pdr' => '10']],
        ['row' => 45, 'old_id' => 5030, 'rates' => ['iso' => '20', 'soa' => '20', 'sic' => '20', 'gdpr' => '20', 'fnc' => '20', 'pdr' => '10'], 'note' => 'Per le pratiche in essere prima della sottoscrizione nel nuovo accordo resta il mantenimento al 30%. -FINANZA AGEVOLATA 15%- APL 10%  - MANTENIMENTO ISO 20% - MANTENIMENTO SOA 10% - MANTENIMENTO GDPR 10%'],
        ['row' => 46, 'old_id' => 16556, 'rates' => ['iso' => '20', 'soa' => '20', 'sic' => '20', 'gdpr' => '20', 'fnc' => '20', 'pdr' => '10'], 'note' => 'Prende il 20% del nostro 30%'],
        ['row' => 47, 'company_name' => 'Teverola Qualifica Group', 'email' => 'teverola@qualificagroup.it', 'phone' => '331 415 9287', 'rates' => ['iso' => '10', 'soa' => '10', 'sic' => '10', 'gdpr' => '10', 'fnc' => '20', 'pdr' => '10'], 'note' => 'Referente: Noemi Communara. Sede 388 486 8459'],
        ['row' => 48, 'old_id' => 18873, 'rates' => ['iso' => '15', 'soa' => '15', 'sic' => '15', 'gdpr' => '15', 'fnc' => '20', 'pdr' => '10']],
        ['row' => 49, 'old_id' => 17581, 'rates' => ['iso' => '20', 'soa' => '20', 'sic' => '20', 'gdpr' => '20', 'fnc' => '20', 'pdr' => '10']],
        ['row' => 50, 'old_id' => 6410, 'rates' => ['iso' => '20', 'soa' => '20', 'sic' => '20', 'gdpr' => '20', 'pdr' => '10']],
        ['row' => 51, 'old_id' => 17582, 'rates' => ['iso' => '15', 'soa' => '15', 'sic' => '15', 'gdpr' => '15', 'fnc' => '20', 'pdr' => '10']],
        ['row' => 52, 'old_id' => 16572, 'rates' => ['iso' => '15', 'soa' => '15', 'sic' => '15', 'gdpr' => '15', 'fnc' => '20', 'pdr' => '10'], 'note' => 'MANTENIMENTO ISO SOA E GDPR 20% - FNC 20%-10% - APL 10%'],
        ['row' => 54, 'first_name' => 'Lucia', 'last_name' => 'Ferraro', 'phone' => '339 520 6816', 'rates' => ['iso' => '10', 'soa' => '10', 'sic' => '10', 'gdpr' => '10', 'fnc' => '20', 'pdr' => '10'], 'note' => 'Mantenimento 5%'],
        ['row' => 55, 'old_id' => 17165, 'rates' => ['iso' => '15', 'soa' => '15', 'sic' => '15', 'gdpr' => '15', 'fnc' => '20', 'pdr' => '10']],
        ['row' => 56, 'first_name' => 'Giovanni', 'last_name' => 'Scifo', 'email' => 'integrappm@gmail.com', 'rates' => ['iso' => '15', 'soa' => '15', 'sic' => '15', 'gdpr' => '15', 'pdr' => '10']],
        ['row' => 57, 'old_id' => 17015, 'rates' => ['iso' => '15', 'soa' => '15', 'sic' => '15', 'gdpr' => '15', 'fnc' => '20', 'pdr' => '10']],
        ['row' => 58, 'old_id' => 18023, 'rates' => ['iso' => '10', 'soa' => '10', 'sic' => '10', 'gdpr' => '10', 'fnc' => '20', 'pdr' => '10'], 'note' => 'APL 10% MANTENIMENTO ISO SOA E GDPR 5%'],
        ['row' => 59, 'old_id' => 17143, 'rates' => ['iso' => '15', 'soa' => '15', 'sic' => '15', 'gdpr' => '15', 'fnc' => '20', 'pdr' => '10']],
        ['row' => 60, 'old_id' => 17139, 'rates' => ['iso' => '15', 'soa' => '15', 'sic' => '15', 'gdpr' => '15', 'fnc' => '20', 'pdr' => '10'], 'note' => 'FINANZA AGEVOLATA 15% APL 10% MANTENIMENTO ISO SOA E GDPR 10%'],
        ['row' => 61, 'old_id' => 4668, 'rates' => ['iso' => '15', 'soa' => '15', 'sic' => '15', 'gdpr' => '15', 'fnc' => '20', 'pdr' => '10']],
        ['row' => 62, 'old_id' => 17113, 'rates' => ['iso' => '15', 'soa' => '15', 'sic' => '15', 'gdpr' => '15', 'fnc' => '20', 'pdr' => '10']],
        ['row' => 63, 'old_id' => 16978, 'rates' => ['iso' => '15', 'soa' => '15', 'sic' => '15', 'gdpr' => '15', 'fnc' => '20', 'pdr' => '10'], 'note' => 'non dovrà essere messo in cc nelle comunicazioni'],
        ['row' => 64, 'old_id' => 9045, 'rates' => ['iso' => '20', 'soa' => '20', 'sic' => '20', 'gdpr' => '20', 'fnc' => '20', 'pdr' => '10']],
        ['row' => 65, 'old_id' => 17181, 'rates' => ['iso' => '15', 'soa' => '15', 'sic' => '15', 'gdpr' => '15', 'fnc' => '20', 'pdr' => '10'], 'note' => '10% sul mantenimento'],
        ['row' => 66, 'old_id' => 17144, 'rates' => ['iso' => '15', 'soa' => '15', 'sic' => '15', 'gdpr' => '15', 'fnc' => '20', 'pdr' => '10'], 'note' => '10% sul mantenimento'],
        ['row' => 67, 'first_name' => 'Antonietta', 'last_name' => 'Migliozzi', 'email' => 'villadibriano2@qualificagroup.it', 'phone' => '348 810 4618', 'rates' => ['iso' => '15', 'soa' => '15', 'sic' => '15', 'gdpr' => '15', 'fnc' => '20', 'pdr' => '10'], 'note' => 'Referente: Aldo Bianco. 10% sul mantenimento'],
        ['row' => 68, 'first_name' => 'Roberto', 'last_name' => 'Nostro', 'email' => 'messina@qualificagroup.it', 'phone' => '393 238 9035', 'rates' => []],
        ['row' => 69, 'old_id' => 17146, 'rates' => ['iso' => '15', 'soa' => '15', 'sic' => '15', 'gdpr' => '15', 'fnc' => '20', 'pdr' => '10'], 'note' => '10% sul mantenimento'],
        ['row' => 70, 'old_id' => 18161, 'rates' => ['iso' => '15', 'soa' => '15', 'sic' => '15', 'gdpr' => '15', 'fnc' => '20', 'pdr' => '10'], 'note' => '10% sul mantenimento'],
        ['row' => 71, 'first_name' => 'Marina', 'last_name' => 'Provviser', 'email' => 'provvi65@gmail.com', 'rates' => ['iso' => '15', 'soa' => '15', 'sic' => '15', 'gdpr' => '15', 'fnc' => '20', 'pdr' => '10'], 'note' => '10% sul mantenimento'],
        ['row' => 72, 'old_id' => 17228, 'rates' => ['iso' => '15', 'soa' => '15', 'sic' => '15', 'gdpr' => '15', 'fnc' => '20', 'pdr' => '10'], 'note' => '10% sul mantenimento'],
        ['row' => 73, 'first_name' => 'Carmela', 'last_name' => 'Giordano', 'rates' => ['iso' => '15', 'soa' => '15', 'sic' => '15', 'gdpr' => '15', 'fnc' => '20', 'pdr' => '10'], 'note' => '10% sul mantenimento'],
        ['row' => 74, 'old_id' => 18784, 'rates' => ['iso' => '15', 'soa' => '15', 'sic' => '15', 'gdpr' => '15', 'fnc' => '20', 'pdr' => '10'], 'note' => '10% sul mantenimento'],
        ['row' => 75, 'old_id' => 18593, 'rates' => ['iso' => '10', 'soa' => '10', 'sic' => '10', 'gdpr' => '10', 'fnc' => '20', 'pdr' => '10'], 'note' => '5% sul mantenimento'],
        ['row' => 76, 'old_id' => 18597, 'rates' => ['iso' => '15', 'soa' => '15', 'sic' => '15', 'gdpr' => '10', 'fnc' => '20', 'pdr' => '10'], 'note' => '10% sul mantenimento'],
        ['row' => 77, 'old_id' => 17077, 'rates' => ['iso' => '15', 'soa' => '15', 'sic' => '15', 'gdpr' => '15', 'fnc' => '15', 'pdr' => '10']],
        ['row' => 78, 'old_id' => 16569, 'rates' => ['iso' => '15', 'soa' => '15', 'sic' => '15', 'gdpr' => '15', 'fnc' => '15', 'pdr' => '10'], 'note' => '10 sul Mantenimento  - apl 10%'],
        ['row' => 79, 'first_name' => 'Matteo', 'last_name' => 'Manganelli', 'email' => 'matteo@yumeh.it', 'phone' => '338 101 1141', 'rates' => ['iso' => '10', 'soa' => '10', 'sic' => '10', 'gdpr' => '10', 'fnc' => '10', 'pdr' => '10']],
        ['row' => 80, 'old_id' => 18587, 'rates' => ['iso' => '10', 'soa' => '10', 'sic' => '10', 'gdpr' => '10', 'fnc' => '10', 'pdr' => '10']],
        ['row' => 81, 'old_id' => 16263, 'rates' => ['iso' => '20', 'soa' => '20', 'sic' => '20', 'gdpr' => '20', 'pdr' => '10'], 'note' => '10% sul mantenimento'],
        ['row' => 82, 'first_name' => 'Giulio', 'last_name' => 'Pallotta', 'email' => 'pallotta.consulenza@yahoo.it', 'phone' => '06 5520 0139', 'rates' => ['iso' => '10', 'soa' => '10', 'sic' => '10', 'gdpr' => '10', 'fnc' => '20', 'pdr' => '10'], 'note' => '5% sul mantenimento'],
        ['row' => 83, 'old_id' => 18153, 'rates' => ['iso' => '10', 'soa' => '10', 'sic' => '10', 'gdpr' => '10', 'fnc' => '20', 'pdr' => '10'], 'note' => '5% sul mantenimento 5% visite mediche'],
        ['row' => 84, 'old_id' => 18804, 'rates' => ['iso' => '15', 'soa' => '15', 'sic' => '15', 'gdpr' => '15', 'fnc' => '20', 'pdr' => '10']],
        ['row' => 85, 'first_name' => 'Lucio', 'last_name' => 'Rinaldi', 'email' => 'rinaldiconsulenza@outlook.it', 'phone' => '+39 327 857 5917', 'rates' => ['iso' => '15', 'soa' => '15', 'sic' => '15', 'gdpr' => '15', 'fnc' => '20', 'pdr' => '10'], 'note' => 'MANTENIMENTI 10%'],
        ['row' => 86, 'old_id' => 18857, 'rates' => ['iso' => '10', 'soa' => '10', 'sic' => '10', 'gdpr' => '10', 'fnc' => '10', 'pdr' => '10']],
        ['row' => 87, 'old_id' => 16521, 'rates' => ['iso' => '10', 'soa' => '10', 'sic' => '10', 'gdpr' => '10', 'fnc' => '20', 'pdr' => '10'], 'note' => 'mantenimento 5%'],
        ['row' => 88, 'first_name' => 'Rosa', 'last_name' => 'Patangelo', 'email' => 'consulenzaaziende@blu.it', 'phone' => '3298441539', 'rates' => ['iso' => '15', 'soa' => '15', 'sic' => '15', 'gdpr' => '15', 'fnc' => '15', 'pdr' => '10'], 'note' => 'Mantenimento 10% e 5% x PDR E SA 8000'],
        ['row' => 89, 'first_name' => 'Antimo', 'last_name' => 'Dalvini', 'email' => 'antimodalvini@yahoo.it', 'phone' => '371 452 7476', 'rates' => ['iso' => '10', 'soa' => '10', 'sic' => '10', 'gdpr' => '10', 'pdr' => '10'], 'note' => '5 % sul mantenimento'],
        ['row' => 90, 'first_name' => 'Vincenzo', 'last_name' => 'Malafronte', 'email' => 'commerciale@vincenzomalafronte.it', 'phone' => '377 129 1060', 'rates' => ['iso' => '15', 'soa' => '15', 'sic' => '15', 'gdpr' => '15', 'fnc' => '20', 'pdr' => '10'], 'note' => '10% sul mantenimento'],
        ['row' => 91, 'old_id' => 19043, 'rates' => ['iso' => '10', 'soa' => '10', 'sic' => '10', 'gdpr' => '10', 'fnc' => '10', 'pdr' => '10'], 'note' => '5 % sul mantenimento'],
        ['row' => 92, 'first_name' => 'Fabio', 'last_name' => 'Cristarelli', 'email' => 'studiocristarelli@libero.it', 'phone' => '+39 338 903 9522', 'rates' => ['iso' => '10', 'soa' => '10', 'sic' => '10', 'gdpr' => '10', 'fnc' => '10', 'pdr' => '10'], 'note' => '5 % sul mantenimento'],
        ['row' => 93, 'first_name' => 'Alfredo', 'last_name' => 'Ambrosino', 'email' => 'ambrosinoalfredo@gmail.com', 'phone' => '3388916430', 'rates' => ['iso' => '15', 'soa' => '15', 'sic' => '15', 'gare' => '10', 'gdpr' => '15', 'fnc' => '20', 'pdr' => '10'], 'note' => 'Mantenimenti ISO 10% - scuole 5%'],
        ['row' => 94, 'first_name' => 'Giuseppe', 'last_name' => 'Cocci', 'rates' => ['iso' => '10', 'soa' => '10', 'sic' => '10', 'gdpr' => '10', 'fnc' => '10', 'pdr' => '10'], 'note' => '5 % sul mantenimento'],
        ['row' => 95, 'old_id' => 19473, 'rates' => ['iso' => '10', 'soa' => '10', 'sic' => '10', 'gare' => '10', 'gdpr' => '10', 'fnc' => '10', 'pdr' => '10']],
    ];
}
