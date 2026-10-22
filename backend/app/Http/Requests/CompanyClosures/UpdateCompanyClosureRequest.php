<?php

namespace App\Http\Requests\CompanyClosures;

use Illuminate\Database\Eloquent\Model;

/**
 * Payload of PUT /api/company-closures/{companyClosure}: the same rules as the creation.
 */
class UpdateCompanyClosureRequest extends StoreCompanyClosureRequest
{
    protected function authorizationModel(): ?Model
    {
        return $this->route('companyClosure');
    }
}
