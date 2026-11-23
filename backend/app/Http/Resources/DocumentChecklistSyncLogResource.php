<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\DocumentChecklistSyncLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DocumentChecklistSyncLog
 */
class DocumentChecklistSyncLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'operation' => $this->operation,
            'outcome' => $this->outcome,
            'http_status' => $this->http_status,
            'attempt' => $this->attempt,
            'message' => $this->message,
            'created_at' => $this->created_at,
        ];
    }
}
