<?php

namespace Database\Seeders\QualificaCatalog;

/**
 * The open "trattative" of Consulenza - Presa Appuntamenti, from the user's sheet
 * "Consulenza_Presa Appuntamenti Trattative 06_11_26 1.xlsx" (user directive
 * 2026-11-07, same shape as AplOrientationRequestCatalogue). Every row is a
 * company (`company`); the category has no product, so no offer line.
 *
 * Cleaned at the source: "Trattativa" -> "In trattativa", "NR" -> "Non risponde",
 * "Senza Fonte" -> "No fonte", phones split one per number (the landline whose
 * leading 0 Excel dropped restored: 0823911970). User decisions 2026-11-07: the
 * "Referente Azienda" becomes a referent of the company (`contact_person`, the
 * title in its notes); the "Citta" goes to the notes (no street to build an
 * address); an existing company is reused only on phone + name.
 */
final class AppointmentSettingRequestCatalogue
{
    public const string CATEGORY = 'Presa Appuntamenti';

    private const string OPERATOR = 'Francesca Forgione';

    private const string SOURCE = 'No fonte';

    private const string CALLBACK = 'Da Richiamare';

    private const string NEGOTIATION = 'In trattativa';

    private const string NO_ANSWER = 'Non risponde';

    /**
     * One entry per sheet row (2..20). Keys as AplOrientationRequestCatalogue, plus
     * company and contact_person {first, last, title?}.
     *
     * @var list<array<string, mixed>>
     */
    public const array ROWS = [
        ['operator' => self::OPERATOR, 'status' => self::CALLBACK, 'source' => self::SOURCE, 'company' => 'Terra di Speranza Società Cooperativa', 'phones' => ['3318761614', '0815021330'], 'callback' => '2026-12-01', 'email' => 'coop.terradisperanza@alice.it', 'contact_person' => ['first' => 'Anna', 'last' => 'Raucci', 'title' => 'Dott.ssa'], 'notes' => "Città: Orta di Atella\nSede operativa: Comunità Educativa Casa Rossi"],
        ['operator' => self::OPERATOR, 'status' => self::NEGOTIATION, 'source' => self::SOURCE, 'company' => 'Ar2', 'phones' => ['3932214937'], 'contact_person' => ['first' => 'Raffaele', 'last' => 'Ariano'], 'notes' => 'Città: Grumo Nevano'],
        ['operator' => self::OPERATOR, 'status' => self::CALLBACK, 'source' => self::SOURCE, 'company' => 'Una Consulting', 'phones' => ['3356261193', '0819761955'], 'callback' => '2026-12-01', 'email' => 'olga.orecchio@una-consulting.it', 'contact_person' => ['first' => 'Olga', 'last' => 'Orecchio'], 'notes' => 'Città: Napoli'],
        ['operator' => self::OPERATOR, 'status' => self::NO_ANSWER, 'source' => self::SOURCE, 'company' => 'Elemed Srl', 'phones' => ['0817148293', '3281881933'], 'email' => 'lucanacar@elemed.it', 'contact_person' => ['first' => 'Luca', 'last' => 'Nacar', 'title' => 'Dr.'], 'notes' => 'Città: Napoli'],
        ['operator' => self::OPERATOR, 'status' => self::CALLBACK, 'source' => self::SOURCE, 'company' => 'IST.SCOL. SORRISO DEI BIMBI IMPRESA SOCIALE SRL', 'phones' => ['3402344914'], 'callback' => '2027-01-11', 'email' => 'sorrisodeibimbi2020@libero.it', 'contact_person' => ['first' => 'Rosa', 'last' => 'Mungiguerra'], 'notes' => "Città: Sant'Antimo"],
        ['operator' => self::OPERATOR, 'status' => self::NO_ANSWER, 'source' => self::SOURCE, 'company' => 'A.G. Renovation S.r.l.', 'phones' => ['0812773634'], 'notes' => 'Città: Caivano'],
        ['operator' => self::OPERATOR, 'status' => self::NO_ANSWER, 'source' => self::SOURCE, 'company' => 'Gesil S.r.l.', 'phones' => ['0818431586'], 'notes' => 'Città: Caivano'],
        ['operator' => self::OPERATOR, 'status' => self::NO_ANSWER, 'source' => self::SOURCE, 'company' => 'Ci. Co Costruzioni Generali S.a.s. di Ciampa Filomena Loredana & C.', 'phones' => ['0818314828'], 'notes' => 'Città: Cardito'],
        ['operator' => self::OPERATOR, 'status' => self::NEGOTIATION, 'source' => self::SOURCE, 'company' => 'Chianese S.r.l.', 'phones' => ['3334780755'], 'email' => 'chianese.srl@virgilio.it', 'contact_person' => ['first' => 'Pasquale', 'last' => 'Chianese', 'title' => 'Arch.'], 'notes' => 'Città: Parete'],
        ['operator' => self::OPERATOR, 'status' => self::NO_ANSWER, 'source' => self::SOURCE, 'company' => 'Bemi Ponteggi Caserta di Micillo Bernardino', 'phones' => ['08119814890', '3298847632'], 'notes' => 'Città: Parete'],
        ['operator' => self::OPERATOR, 'status' => self::NEGOTIATION, 'source' => self::SOURCE, 'company' => 'Ciardiello Marmi S.r.l.', 'phones' => ['0823911970'], 'notes' => 'Città: Piedimonte Matese'],
        ['operator' => self::OPERATOR, 'status' => self::CALLBACK, 'source' => self::SOURCE, 'company' => 'Brino Costruzioni', 'phones' => ['3932197510', '08247438871'], 'callback' => '2026-12-01', 'email' => 'tommasobrino@tim.it', 'notes' => 'Città: Ruviano'],
        ['operator' => self::OPERATOR, 'status' => self::NEGOTIATION, 'source' => self::SOURCE, 'company' => 'EFFECOSTRUZIONI DI GRAZIANO GIANLUCA', 'phones' => ['3917310200'], 'notes' => 'Città: San Marcellino'],
        ['operator' => self::OPERATOR, 'status' => self::CALLBACK, 'source' => self::SOURCE, 'company' => 'Impiantistica GT', 'phones' => ['3393238617'], 'callback' => '2026-11-09', 'notes' => 'Città: San Nicola La Strada'],
        ['operator' => self::OPERATOR, 'status' => self::CALLBACK, 'source' => self::SOURCE, 'company' => 'T&T Impianti Elettrici', 'phones' => ['3383549278', '08231490400'], 'callback' => '2026-12-14', 'notes' => 'Città: San Nicola La Strada'],
        ['operator' => self::OPERATOR, 'status' => self::CALLBACK, 'source' => self::SOURCE, 'company' => 'M.E.N.S. Costruzioni', 'phones' => ['3337143883', '0823742506'], 'callback' => '2026-11-16', 'notes' => "Città: Sant'Angelo d'Alife"],
        ['operator' => self::OPERATOR, 'status' => self::NEGOTIATION, 'source' => self::SOURCE, 'company' => 'Cristal Impianti S.r.l.', 'phones' => ['0813764203', '3792527882'], 'notes' => 'Città: Succivo'],
        ['operator' => self::OPERATOR, 'status' => self::CALLBACK, 'source' => self::SOURCE, 'company' => 'Costruzioni e Ristrutturazioni Paciello Srls', 'phones' => ['3751034482'], 'callback' => '2026-12-01', 'notes' => 'Città: Teverola'],
        ['operator' => self::OPERATOR, 'status' => self::CALLBACK, 'source' => self::SOURCE, 'company' => 'La Torre Servizi alle Imprese S.R.L.S.', 'phones' => ['3342083269'], 'callback' => '2026-11-16', 'notes' => 'Città: Crispano'],
    ];
}
