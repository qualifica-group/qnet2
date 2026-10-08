<?php

namespace Database\Seeders\DemoCatalog;

use App\Enums\CommissionRecipientRole as Role;
use App\Enums\CommissionType as Type;

/**
 * Pure data of DemoCommissionShowcaseSeeder: one commessa per commission case,
 * with round figures so every number on the "Dati contrattuali" tab can be
 * checked by hand. The cases are keyed by their commessa title, which is also
 * the idempotency key of the seeder.
 */
final class DemoCommissionShowcaseCatalogue
{
    public const string TITLE_PREFIX = 'Demo commissioni - ';

    public const string SUPPLIER_NAME = 'Demo showcase - Fornitore Srl';

    public const string CUSTOMER_NAME = 'Demo showcase - Cliente';

    public const string COST_PRODUCT = 'Demo showcase - Costo imputato';

    public const string TRAINING_TYPOLOGY_CODE = 'demo_training';

    public const string TRAINING_TYPOLOGY_NAME = 'Formazione (demo)';

    public const string TRAINING_TYPOLOGY_COLOR = 'amber';

    /** Leaf category the showcase products are filed under (DemoCategoryCatalogue). */
    public const string CATEGORY = 'Consulenza HR';

    /**
     * Product name => [typology code, unit price, has the demo supplier].
     *
     * @var array<string, array{0: string, 1: float, 2: bool}>
     */
    public const array PRODUCTS = [
        'Demo showcase - Consulenza' => ['consultancy', 1000.0, true],
        'Demo showcase - Ente percentuale' => ['institution', 5000.0, true],
        'Demo showcase - Ente fissa' => ['institution', 2000.0, true],
        'Demo showcase - Ente senza fornitore' => ['institution', 2000.0, false],
        'Demo showcase - Formazione' => [self::TRAINING_TYPOLOGY_CODE, 1000.0, true],
    ];

    /**
     * Product name => its PRODUCT-scope rules [role, type, value]. The Supplier
     * rule on the training product exists on purpose: its typology has the
     * calculation switched off, so it must NOT produce a commission (spec 0202 D-6).
     *
     * @var array<string, list<array{0: Role, 1: Type, 2: string}>>
     */
    public const array RULES = [
        'Demo showcase - Consulenza' => [
            [Role::Commercial, Type::Percentage, '5'],
            [Role::Reporter, Type::Percentage, '3'],
            [Role::Supplier, Type::Percentage, '10'],
        ],
        'Demo showcase - Ente percentuale' => [
            [Role::Supplier, Type::Percentage, '20'],
            [Role::Commercial, Type::Percentage, '5'],
        ],
        'Demo showcase - Ente fissa' => [
            [Role::Supplier, Type::FixedAmount, '300'],
        ],
        'Demo showcase - Ente senza fornitore' => [
            [Role::Supplier, Type::Percentage, '20'],
        ],
        'Demo showcase - Formazione' => [
            [Role::Supplier, Type::Percentage, '10'],
            [Role::Supervisor, Type::FixedAmount, '50'],
        ],
    ];

    /**
     * Commessa title (without prefix) => lines [product, quantity, unit price,
     * imputed cost or null, payment status old_id or null, has unpaid]. The
     * unit price repeats the product price except where the case needs another.
     *
     * @var array<string, list<array{0: string, 1: float, 2: float, 3: float|null, 4: int|null, 5: bool}>>
     */
    public const array CASES = [
        'Consulenza - commissione Fornitore pagata' => [
            ['Demo showcase - Consulenza', 1.0, 1000.0, null, null, false],
        ],
        'Ente - commissione Fornitore ricevuta %' => [
            ['Demo showcase - Ente percentuale', 1.0, 5000.0, 200.0, null, false],
        ],
        'Ente - commissione Fornitore ricevuta fissa' => [
            ['Demo showcase - Ente fissa', 1.0, 2000.0, null, null, false],
        ],
        'Ente - fornitore mancante' => [
            ['Demo showcase - Ente senza fornitore', 1.0, 2000.0, null, null, false],
        ],
        'Tipologia senza calcolo Fornitore' => [
            ['Demo showcase - Formazione', 1.0, 1500.0, null, null, false],
        ],
        'Mista - tre tipologie' => [
            ['Demo showcase - Consulenza', 1.0, 2000.0, 300.0, 3, false],
            ['Demo showcase - Ente percentuale', 1.0, 5000.0, 200.0, 5, false],
            ['Demo showcase - Formazione', 2.0, 1000.0, null, 9, true],
        ],
    ];

    public const string PAYMENT_AGREEMENT = 'Acconto 50% alla firma, saldo a 30 giorni';
}
