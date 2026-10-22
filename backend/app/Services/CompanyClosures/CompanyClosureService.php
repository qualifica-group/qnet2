<?php

declare(strict_types=1);

namespace App\Services\CompanyClosures;

use App\Enums\CompanyClosureKind;
use App\Models\CompanyClosure;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Create, update and delete of company closures (spec 0304 D-13, D-15). A
 * collective leave generates its absences on every write; a started closure
 * is frozen (409), except the yearly ones.
 */
final class CompanyClosureService
{
    /** Relations the CompanyClosureResource reads (lazy loading is forbidden). */
    public const array RELATIONS = ['absenceType:id,name', 'operationalSites.addresses.city'];

    public function __construct(private readonly CollectiveLeaveGenerator $generator) {}

    /**
     * @param  array<string, mixed>  $attributes  validated payload
     * @return array{closure: CompanyClosure, generation: array<string, mixed>|null}
     */
    public function create(array $attributes, User $actor): array
    {
        return DB::transaction(function () use ($attributes, $actor): array {
            $closure = CompanyClosure::query()->create([...$this->columns($attributes), 'created_by' => $actor->id]);

            return $this->finish($closure, $attributes, $actor);
        });
    }

    /**
     * @param  array<string, mixed>  $attributes  validated payload
     * @return array{closure: CompanyClosure, generation: array<string, mixed>|null}
     */
    public function update(CompanyClosure $closure, array $attributes, User $actor): array
    {
        return DB::transaction(function () use ($closure, $attributes, $actor): array {
            $closure = CompanyClosure::query()->lockForUpdate()->findOrFail($closure->id);
            $this->assertEditable($closure);

            $closure->update($this->columns($attributes));

            return $this->finish($closure, $attributes, $actor);
        });
    }

    public function delete(CompanyClosure $closure): void
    {
        DB::transaction(function () use ($closure): void {
            $closure = CompanyClosure::query()->lockForUpdate()->findOrFail($closure->id);
            $this->assertEditable($closure);

            $this->generator->purge($closure);
            $closure->operationalSites()->detach();
            $closure->delete();
        });
    }

    public function detail(CompanyClosure $closure): CompanyClosure
    {
        return $closure->load(self::RELATIONS)->loadCount('absenceRequests');
    }

    /**
     * Sites sync and (re)generation, then the closure as the resource reads it.
     *
     * @param  array<string, mixed>  $attributes
     * @return array{closure: CompanyClosure, generation: array<string, mixed>|null}
     */
    private function finish(CompanyClosure $closure, array $attributes, User $actor): array
    {
        $closure->operationalSites()->sync($closure->applies_to_all_sites ? [] : $attributes['operational_site_ids']);
        $closure->unsetRelation('operationalSites');

        $generation = $closure->kind === CompanyClosureKind::CollectiveLeave
            ? $this->generator->regenerate($closure, $actor)
            : $this->clearGenerated($closure);

        return ['closure' => $this->detail($closure), 'generation' => $generation];
    }

    /** A closure turned into a holiday keeps none of the absences it generated. */
    private function clearGenerated(CompanyClosure $closure): ?array
    {
        $this->generator->purge($closure);

        return null;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function columns(array $attributes): array
    {
        $isHoliday = $attributes['kind'] === CompanyClosureKind::Holiday->value;

        return [
            'name' => $attributes['name'],
            'starts_on' => $attributes['starts_on'],
            'ends_on' => $attributes['ends_on'],
            'kind' => $attributes['kind'],
            'absence_type_id' => $isHoliday ? null : $attributes['absence_type_id'],
            'applies_to_all_sites' => (bool) $attributes['applies_to_all_sites'],
            'repeats_yearly' => $isHoliday && ($attributes['repeats_yearly'] ?? false),
        ];
    }

    /** A started closure is frozen, a yearly one stays editable (valid from the save on). */
    private function assertEditable(CompanyClosure $closure): void
    {
        abort_if($closure->isStarted() && ! $closure->repeats_yearly, 409, __('company_closures.started'));
    }
}
