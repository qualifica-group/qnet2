<?php

declare(strict_types=1);

namespace App\Http\Requests\Treasury;

/** GET /api/financial-accounts/{financialAccount}/statement/export: same parameters, `treasury-movements.export`. */
class StatementExportRequest extends StatementRequest
{
    protected function requiredPermission(): string
    {
        return 'treasury-movements.export';
    }
}
