<?php

declare(strict_types=1);

namespace Integrations\Formalab\Http\Controllers;

use App\Http\Controllers\Abstract\BaseApiController;
use Illuminate\Http\JsonResponse;
use Integrations\Formalab\Http\Requests\ClassSearchRequest;
use Integrations\Formalab\Http\Resources\FormalabClassResource;
use Integrations\Formalab\Models\FormalabClass;
use Throwable;

class ClassController extends BaseApiController
{
    /**
     * GET /api/integrations/formalab/classes: search in the local catalogue.
     */
    public function index(ClassSearchRequest $request): JsonResponse
    {
        try {
            $search = trim((string) $request->validated('search', ''));
            $limit = (int) ($request->validated('limit') ?? ClassSearchRequest::DEFAULT_LIMIT);

            $classes = FormalabClass::query()
                ->when($search !== '', static function ($query) use ($search): void {
                    $pattern = '%'.addcslashes($search, '%_\\').'%';
                    $query->where(static fn ($inner) => $inner
                        ->where('code', 'like', $pattern)
                        ->orWhere('title', 'like', $pattern)
                        ->orWhere('site_city', 'like', $pattern));
                })
                ->orderByDesc('start_date')
                ->orderByDesc('edition_id')
                ->limit($limit)
                ->get();

            return $this->ok(FormalabClassResource::collection($classes));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
