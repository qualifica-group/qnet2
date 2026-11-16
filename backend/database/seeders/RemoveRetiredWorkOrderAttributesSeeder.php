<?php

namespace Database\Seeders;

use App\Services\AttributeRemover;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * One-shot (user request 2026-11-16):
 * `php artisan db:seed --class=RemoveRetiredWorkOrderAttributesSeeder --force`.
 *
 * Deletes the commessa flexible fields "Accordo sui Pagamenti", "Insoluti",
 * "Comunicazioni Email Commerciale", "Proforma emessa" and "Totale commessa
 * manuale": their values, category assignments, layout placements, options
 * and the fields themselves (AttributeRemover). The payment agreement and the
 * unpaid flag stay on the per-product payments of the commessa (spec 0403).
 * The legacy no longer sends them (AttributeMigrationController::RETIRED_CODES),
 * so a new import does not bring them back. Re-running it changes nothing.
 */
class RemoveRetiredWorkOrderAttributesSeeder extends Seeder
{
    /** @var list<string> */
    public const array ATTRIBUTE_CODES = [
        'accordo_pagamento',
        'insoluti',
        'comunicazione_email',
        'status_contabile',
        'totale_commessa_manuale',
    ];

    public function run(AttributeRemover $remover): void
    {
        $result = DB::transaction(fn (): array => $remover->remove(self::ATTRIBUTE_CODES));

        $this->command?->info(sprintf(
            'Campi eliminati: %d. Valori tolti da %d record, %d assegnazioni a categorie, %d layout aggiornati.',
            $result['deleted'],
            $result['values'],
            $result['detached'],
            $result['layouts'],
        ));
    }
}
