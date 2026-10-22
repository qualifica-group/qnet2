<?php

use App\Models\PurchaseRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// Spec 0303: a record the legacy sends with `orphan_reason` (nothing to import
// it into) fails with that reason under its own id, whatever the source; the
// other records of the page are imported as usual. Fixtures in
// tests/Helpers/PurchaseRequestMigrationHelpers.php.

beforeEach(function () {
    seedMigrationsConfig();
    legacyPurchaseRequestAnchors();
});

it('fails an orphan with the legacy reason and imports the other records', function () {
    $run = runLegacyPurchaseRequestsImport([
        legacyPurchaseRequestRecord(),
        ['id' => 'rda_detail:161', 'orphan_reason' => 'Riga RDA 161 (mouse): la RDA 49 non esiste nel legacy.', 'warnings' => []],
    ]);

    expect($run->created_rows)->toBe(1)
        ->and($run->failed_rows)->toBe(1)
        ->and(PurchaseRequest::query()->count())->toBe(1)
        ->and(collect($run->report)->where('level', 'error')->values()->all())->toBe([[
            'old_id' => 'rda_detail:161',
            'level' => 'error',
            'message' => 'Failed to import the record: Riga RDA 161 (mouse): la RDA 49 non esiste nel legacy.',
        ]]);
});
