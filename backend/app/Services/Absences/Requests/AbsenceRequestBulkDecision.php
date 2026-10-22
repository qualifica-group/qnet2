<?php

declare(strict_types=1);

namespace App\Services\Absences\Requests;

use App\Models\AbsenceRequest;
use App\Models\User;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Approve or reject many requests (spec 0304 D-17): each id goes through the
 * same transition as the single decision, in its own transaction, so
 * authorization, state, notification and activity log are identical. An id the
 * actor cannot see is reported as not found, never as forbidden.
 */
final class AbsenceRequestBulkDecision
{
    public function __construct(
        private readonly AbsenceRequestTransitions $transitions,
        private readonly AbsenceRequestVisibility $visibility,
    ) {}

    /**
     * @param  list<int>  $ids
     * @return array{decided: list<int>, failed: list<array{id: int, message: string}>}
     */
    public function decide(User $actor, string $action, array $ids, ?string $reason): array
    {
        $decided = [];
        $failed = [];

        foreach ($ids as $id) {
            try {
                $request = AbsenceRequest::query()->find($id);
                abort_if($request === null || ! $this->visibility->canView($actor, $request), 404, __('absence_requests.bulk_not_found'));

                $action === 'approve'
                    ? $this->transitions->approve($request, $actor)
                    : $this->transitions->reject($request, $actor, (string) $reason);

                $decided[] = $id;
            } catch (HttpException $exception) {
                $failed[] = ['id' => $id, 'message' => $exception->getMessage()];
            }
        }

        return ['decided' => $decided, 'failed' => $failed];
    }
}
