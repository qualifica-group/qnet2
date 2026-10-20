<?php

namespace Database\Seeders\QualificaCatalog;

/**
 * The products of the "GDPR" branch, transcribed from the "SERVIZI GDPR" list
 * (user directive 2026-10-20): one row per product, filed on the subcategory
 * of its "Categoria" column ("DPO" is the "Nomina DPO" line), with its price,
 * its "Mesi scadenza" and, where the list gives one, its description. No
 * supplier is named; the row the list marks in yellow ("GDPR_", under
 * "PROGETTI-") is left out. Pure data, seeded by BranchProducts with the 22%
 * VAT rate and the "Consulenza" typology (GdprCatalogue::TYPOLOGIES).
 *
 * Names are the list's titles verbatim.
 */
final class GdprProductCatalogue
{
    /**
     * Category name => product name => its description, price, supplier and "Mesi scadenza".
     *
     * @var array<string, array<string, array{description?: string, price: float, supplier: string|null, expiration_months: int|null}>>
     */
    public const array PRODUCTS = [
        GdprCatalogue::GDPR => [
            'Attività di Verifica e Mantenimento annuo adeguamento GDPR 2016/679 2° anno' => ['price' => 500.0, 'supplier' => null, 'expiration_months' => 12],
            'GDPR: Predisposizione documentazione + Analisi ed interventi sui processi aziendali + Formazione del personale I ANNO' => ['price' => 0.0, 'supplier' => null, 'expiration_months' => 0],
            'Attività di Verifica e Mantenimento annuo adeguamento GDPR 2016/679 3° anno' => ['price' => 0.0, 'supplier' => null, 'expiration_months' => 24],
            'GDPR: Predisposizione documentazione + Analisi ed interventi sui processi aziendali I ANNO' => ['price' => 0.0, 'supplier' => null, 'expiration_months' => 0],
            'Whistleblowing' => ['price' => 0.0, 'supplier' => null, 'expiration_months' => 0],
            '- Parere legale scritto sollevato in relazione alla presunta violazione dell’art. 6, comma 1, del Regolamento (UE) 2016/679 e l\'art. 130 co. 2 del Codice Privacy,' => ['price' => 0.0, 'supplier' => null, 'expiration_months' => 0],
            'Integrazione GDPR' => ['price' => 0.0, 'supplier' => null, 'expiration_months' => 0],
        ],
        GdprCatalogue::DPO_APPOINTMENT => [
            'DPO_1° anno' => ['description' => 'Predisposizione documentazione + Analisi ed interventi sui processi aziendali + Formazione del personale', 'price' => 0.0, 'supplier' => null, 'expiration_months' => 0],
            'DPO_2° anno' => ['description' => 'Revisione documentazione + Analisi ed interventi sui processi aziendali + Formazione del Personale', 'price' => 0.0, 'supplier' => null, 'expiration_months' => 12],
            'DPO_3° anno' => ['description' => 'Revisione Sistema + Analisi ed interventi sui processi aziendali + Formazione del Personale', 'price' => 0.0, 'supplier' => null, 'expiration_months' => 24],
            'Supporto e manutenzione software_1° anno' => ['price' => 0.0, 'supplier' => null, 'expiration_months' => 0],
            'Supporto e manutenzione software_2° anno' => ['price' => 0.0, 'supplier' => null, 'expiration_months' => 12],
            'Supporto e manutenzione software_3° anno' => ['price' => 0.0, 'supplier' => null, 'expiration_months' => 24],
            'Canone cloud_1° anno' => ['price' => 0.0, 'supplier' => null, 'expiration_months' => 0],
            'Canone cloud_2° anno' => ['price' => 0.0, 'supplier' => null, 'expiration_months' => 12],
            'Canone cloud_3° anno' => ['price' => 0.0, 'supplier' => null, 'expiration_months' => 24],
        ],
    ];
}
