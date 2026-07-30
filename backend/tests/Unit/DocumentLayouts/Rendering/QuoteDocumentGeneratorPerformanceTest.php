<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

require_once __DIR__.'/RenderingTestSupport.php';

// spec 0070 AC-321 — a 50-line quote generates in under the declared
// threshold (constant, not a magic number inline in the assertion).

const DL_GENERATION_TIME_LIMIT_SECONDS = 2.0;

it('generates a 50-line quote document in under '.DL_GENERATION_TIME_LIMIT_SECONDS.'s (AC-321)', function () {
    $quote = dlrFullQuote();

    for ($i = 0; $i < 50; $i++) {
        dlrAddLine($quote, $i);
    }

    $config = dlrDocConfig(['body' => ['blocks' => [
        dlrProductsTableBlock([
            dlrProductColumn(['code', 'name'], 'Codice/Nome', 30, '-'),
            dlrProductColumn(['quantity'], 'Quantita', 15, align: 'right'),
            dlrProductColumn(['unit_price'], 'Prezzo', 20, align: 'right'),
            dlrProductColumn(['vat_rate'], 'IVA', 15, align: 'center'),
            dlrProductColumn(['total_amount'], 'Totale', 20, align: 'right'),
        ], totals: ['show' => true, 'rows' => [
            ['label' => 'Totale imponibile', 'variable' => '{totals.revenue_net}', 'bold' => true],
        ]]),
    ]]]);

    $startedAt = microtime(true);
    dlrRender($config, $quote->fresh(['offerLines']));
    $elapsedSeconds = microtime(true) - $startedAt;

    expect($elapsedSeconds)->toBeLessThan(DL_GENERATION_TIME_LIMIT_SECONDS);
});
