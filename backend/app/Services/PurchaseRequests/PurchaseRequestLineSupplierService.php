<?php

declare(strict_types=1);

namespace App\Services\PurchaseRequests;

use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestLine;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Mass supplier assignment on lines (spec 0209, D-4). All or nothing: lines and
 * requests are locked, every line is checked (403 out of reach, 409 closed,
 * 422 frozen supplier per PurchaseRequestCapabilityResolver::canUpdateSupplier)
 * and only then is anything written.
 */
class PurchaseRequestLineSupplierService
{
    public function __construct(
        private readonly PurchaseRequestCapabilityResolver $capabilities,
        private readonly PurchaseRequestLineLocker $locker,
    ) {}

    /**
     * @param  array<int, int>  $lineIds  in request order (the 422 keys follow it)
     * @return array{updated_count: int}
     */
    public function assign(User $actor, array $lineIds, int $supplierId): array
    {
        return DB::transaction(function () use ($actor, $lineIds, $supplierId): array {
            // Step 1: lock the lines and their requests
            $lines = $this->locker->lines($lineIds);
            $requests = $this->locker->requests($lines);

            // Step 2: refuse the whole change if any line is out of reach, closed or frozen
            $this->assertAllowed($actor, $lineIds, $lines, $requests);

            // Step 3: write
            foreach ($lines as $line) {
                $line->update(['supplier_id' => $supplierId]);
            }

            return ['updated_count' => $lines->count()];
        });
    }

    /**
     * @param  array<int, int>  $lineIds
     * @param  Collection<int, PurchaseRequestLine>  $lines
     * @param  Collection<int, PurchaseRequest>  $requests
     */
    private function assertAllowed(User $actor, array $lineIds, Collection $lines, Collection $requests): void
    {
        $ordered = array_map(fn (int $id): PurchaseRequestLine => $lines->get($id) ?? abort(404), $lineIds);
        $mayUpdate = fn (PurchaseRequest $request): bool => ! $request->isClosed() && Gate::forUser($actor)->allows('update', $request);

        foreach ($ordered as $line) {
            $request = $requests->get($line->purchase_request_id);

            if ($this->capabilities->capabilities($actor, $request) === [] && ! Gate::forUser($actor)->allows('update', $request)) {
                throw new AuthorizationException;
            }
        }

        foreach ($ordered as $line) {
            if ($requests->get($line->purchase_request_id)->isClosed()) {
                abort(409, 'This purchase request is closed and can no longer be changed.');
            }
        }

        $errors = [];

        foreach ($ordered as $index => $line) {
            $request = $requests->get($line->purchase_request_id);

            if (! $this->capabilities->canUpdateSupplier($actor, $request, $line, $mayUpdate($request))) {
                $errors["line_ids.{$index}"] = ['The supplier of this line can no longer be changed.'];
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
