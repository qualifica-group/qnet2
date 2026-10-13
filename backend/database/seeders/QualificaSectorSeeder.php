<?php

namespace Database\Seeders;

use App\DataObjects\Sectors\CreateSectorData;
use App\Models\Sector;
use App\Services\SectorService;
use Database\Seeders\QualificaCatalog\SectorCatalogue;
use Illuminate\Database\Seeder;

/**
 * The client's EA sectors (spec 0213): hard-coded reference data, so a step of
 * QualificaProductionDataSeeder with no `Demo` prefix. Every row is created
 * ACTIVE, as a root, with its code verbatim; the legacy sectors the import
 * brings in are deactivated by QualificaLegacyImportSeeder instead.
 *
 * Idempotent on the `code` (D-5): a code already present is left as it is, so
 * a rename or a deactivation made from the module survives a re-run.
 */
class QualificaSectorSeeder extends Seeder
{
    public function __construct(private readonly SectorService $service) {}

    public function run(): void
    {
        $existingCodes = Sector::query()->whereNotNull('code')->pluck('code')->all();

        foreach (SectorCatalogue::SECTORS as $code => $name) {
            // PHP turns numeric-string keys ('01' stays, '10' does not) into
            // ints: the code is always written back as a string.
            $code = (string) $code;

            if (in_array($code, $existingCodes, true)) {
                continue;
            }

            $this->service->create(new CreateSectorData(name: $name, code: $code));
        }
    }
}
