<?php

namespace Database\Seeders\QualificaCatalog;

/**
 * The client's grading as a CCNL (spec 0272 D-3): the "Livello" column of the
 * "Pianta Organica", level code => the sheet's own legend, in level order.
 */
final class CollectiveAgreementCatalogue
{
    public const string NAME = 'Pianta Organica Qualifica Group';

    /** @var array<string, string> */
    public const array LEVELS = [
        'Livello I' => 'Responsabile di settore o unità',
        'Livello II' => 'Coordinatore, supervisor o referente',
        'Livello III' => 'Risorsa operativa senior',
        'Livello IV' => 'Risorsa operativa junior',
    ];
}
