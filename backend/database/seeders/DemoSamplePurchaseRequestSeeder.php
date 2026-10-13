<?php

namespace Database\Seeders;

use App\DataObjects\PurchaseRequests\PurchaseRequestData;
use App\Enums\PurchaseRequestLineStatus;
use App\Models\BusinessFunction;
use App\Models\CompanySite;
use App\Models\OperationalSite;
use App\Models\PurchaseRequestPriority;
use App\Models\Registry;
use App\Models\User;
use App\Models\VatRate;
use App\Services\PurchaseRequests\PurchaseRequestLineStatusService;
use App\Services\PurchaseRequests\PurchaseRequestService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * Additive purchase requests (spec 0208) for DemoAccountingSampleSeeder: each
 * run appends a batch created through PurchaseRequestService, then walks the
 * lines through PurchaseRequestLineStatusService so the batch shows every
 * line status. Approved lines keep their supplier, ready to be ordered by
 * DemoSamplePurchaseOrderSeeder. Skipped with a warning when the reference
 * data it hangs on is missing.
 */
class DemoSamplePurchaseRequestSeeder extends Seeder
{
    public const int DEFAULT_REQUESTS = 8;

    private const string REJECTION_REASON = 'Fuori budget (demo).';

    /** The status each line ends in, cycled over the batch's lines. */
    private const array LINE_OUTCOMES = [
        PurchaseRequestLineStatus::Approved,
        PurchaseRequestLineStatus::PendingApproval,
        PurchaseRequestLineStatus::Approved,
        PurchaseRequestLineStatus::Rejected,
        PurchaseRequestLineStatus::Approved,
        PurchaseRequestLineStatus::OnHold,
    ];

    /** @var array<int, array{0: string, 1: array<int, array{0: string, 1: string, 2: string}>}> [subject, [[description, quantity, unit price]]] */
    private const array REQUESTS = [
        ['Dotazione informatica nuovi assunti', [['Portatile 14 pollici', '2', '950.00'], ['Docking station USB-C', '2', '120.00'], ['Monitor 27 pollici', '2', '280.00']]],
        ['Cancelleria trimestrale', [['Risme di carta A4', '30', '4.50'], ['Toner stampante', '4', '68.00']]],
        ['Materiale didattico corso sicurezza', [['Dispense stampate', '40', '6.20'], ['Kit DPI dimostrativi', '5', '45.00']]],
        ['Arredi sala formazione', [['Sedia con ribaltina', '12', '89.00'], ['Lavagna magnetica', '1', '310.00'], ['Proiettore', '1', '720.00']]],
        ['Licenze software annuali', [['Suite di produttivita', '10', '126.00'], ['Antivirus endpoint', '10', '32.00']]],
        ['Servizi di pulizia straordinaria', [['Intervento sanificazione', '2', '380.00']]],
        ['Trasferta docenti', [['Biglietti treno', '4', '79.00'], ['Pernottamento', '3', '95.00']]],
        ['Manutenzione climatizzatori', [['Controllo e ricarica gas', '3', '140.00'], ['Sostituzione filtri', '6', '22.00']]],
    ];

    public function __construct(
        private readonly PurchaseRequestService $requests,
        private readonly PurchaseRequestLineStatusService $statuses,
    ) {}

    public function run(User $actor, int $requests = self::DEFAULT_REQUESTS): void
    {
        // Step 1: the reference data every request hangs on
        $context = $this->context();

        if ($context === null) {
            $this->command?->warn('Purchase requests skipped: users, company sites, operational sites, functions, suppliers or VAT rates are missing.');

            return;
        }

        // Step 2: the requests, every line born pending approval
        $lineIds = collect(range(0, $requests - 1))
            ->flatMap(fn (int $index): array => $this->create($index, $context, $actor));

        // Step 3: walk the lines to their outcome, one mass change per status
        $lineIds->values()
            ->groupBy(fn (int $id, int $position): string => self::LINE_OUTCOMES[$position % count(self::LINE_OUTCOMES)]->value)
            ->except(PurchaseRequestLineStatus::PendingApproval->value)
            ->each(function (Collection $ids, string $status) use ($actor): void {
                $to = PurchaseRequestLineStatus::from($status);
                $this->statuses->change($actor, $ids->all(), $to, $to === PurchaseRequestLineStatus::Rejected ? self::REJECTION_REASON : null);
            });
    }

    /**
     * @return array{users: Collection<int, User>, sites: Collection<int, CompanySite>, operational_site_ids: Collection<int, int>, functions: Collection<int, BusinessFunction>, supplier_ids: Collection<int, int>, vat_rate_id: int, priority_ids: Collection<int, int>}|null
     */
    private function context(): ?array
    {
        $context = [
            'users' => User::query()->orderBy('id')->get(['id']),
            'sites' => CompanySite::query()->whereNotNull('company_id')->orderBy('id')->get(['id', 'company_id']),
            'operational_site_ids' => OperationalSite::query()->orderBy('id')->pluck('id'),
            'functions' => BusinessFunction::query()->orderBy('id')->get(['id', 'manager_id']),
            'supplier_ids' => Registry::query()->where('is_supplier', true)->orderBy('id')->pluck('id'),
            'vat_rate_id' => VatRate::query()->orderByDesc('rate')->value('id'),
            'priority_ids' => PurchaseRequestPriority::query()->where('is_active', true)->orderBy('sort_order')->pluck('id'),
        ];

        $missing = collect($context)->contains(fn (mixed $value): bool => $value === null || ($value instanceof Collection && $value->isEmpty()));

        return $missing ? null : $context;
    }

    /**
     * @param  array{users: Collection<int, User>, sites: Collection<int, CompanySite>, operational_site_ids: Collection<int, int>, functions: Collection<int, BusinessFunction>, supplier_ids: Collection<int, int>, vat_rate_id: int, priority_ids: Collection<int, int>}  $context
     * @return array<int, int> the ids of the new lines
     */
    private function create(int $index, array $context, User $actor): array
    {
        [$subject, $lines] = self::REQUESTS[$index % count(self::REQUESTS)];
        $site = $context['sites']->random();
        $function = $context['functions']->random();
        $priorityIds = $context['priority_ids'];

        $request = $this->requests->create(new PurchaseRequestData([
            'subject' => sprintf('%s (demo)', $subject),
            'requested_at' => now()->subDays(random_int(0, 60))->toDateString(),
            'purchase_request_priority_id' => $priorityIds[$index % count($priorityIds)],
            'requester_id' => $context['users']->random()->id,
            'function_manager_id' => $function->manager_id ?? $actor->id,
            'company_id' => $site->company_id,
            'company_site_id' => $site->id,
            'operational_site_id' => $context['operational_site_ids']->random(),
            'business_function_id' => $function->id,
        ], array_map(fn (array $line): array => [
            'product_id' => null,
            'description' => $line[0],
            'supplier_id' => $context['supplier_ids']->random(),
            'reason' => null,
            'unit_of_measure_id' => null,
            'quantity' => $line[1],
            'unit_price' => $line[2],
            'vat_rate_id' => $context['vat_rate_id'],
        ], $lines)), $actor);

        return $request->lines->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();
    }
}
