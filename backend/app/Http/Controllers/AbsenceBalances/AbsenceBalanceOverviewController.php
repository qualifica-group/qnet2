<?php

namespace App\Http\Controllers\AbsenceBalances;

use App\Exports\AbsenceBalances\AbsenceBalanceOverviewExporter;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\AbsenceBalances\AbsenceBalanceOverviewRequest;
use App\Http\Requests\AbsenceBalances\ExportAbsenceBalanceOverviewRequest;
use App\Services\Absences\Balances\AbsenceBalanceOverviewBuilder;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

/**
 * HR overview of the balances of every person (spec 0304 D-3, D-12):
 * GET /api/absence-balances/overview (paginated) and its file export.
 */
class AbsenceBalanceOverviewController extends BaseApiController
{
    public function __construct(
        private readonly AbsenceBalanceOverviewBuilder $overview,
        private readonly AbsenceBalanceOverviewExporter $exporter,
    ) {}

    public function index(AbsenceBalanceOverviewRequest $request): JsonResponse
    {
        try {
            $year = $request->year();
            $types = $this->overview->types();
            $page = $this->overview->people($request->filters())->paginate(
                $request->integer('per_page', AbsenceBalanceOverviewRequest::DEFAULT_PER_PAGE),
                ['users.id', 'users.name'],
                'page',
                $request->integer('page', 1),
            );

            return response()->json([
                'success' => true,
                'message' => 'OK',
                'data' => [
                    'year' => $year,
                    'types' => $types->map(static fn ($type): array => ['id' => $type->id, 'name' => $type->name, 'color' => $type->color])->all(),
                    'people' => $this->overview->rowsFor($page->getCollection(), $types, $year),
                ],
                'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
            ]);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    public function export(ExportAbsenceBalanceOverviewRequest $request): BinaryFileResponse|JsonResponse
    {
        try {
            $format = $request->exportFormat();
            $year = $request->year();
            $path = tempnam(sys_get_temp_dir(), 'absence-balances-');

            $this->exporter->write($format, $path, $year, $request->filters());

            return response()
                ->download($path, "absence-balances-{$year}.{$format->extension()}", ['Content-Type' => $format->contentType()])
                ->deleteFileAfterSend();
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
