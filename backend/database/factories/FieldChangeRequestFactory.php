<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\FieldChangeRequest;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * @extends Factory<FieldChangeRequest>
 */
class FieldChangeRequestFactory extends Factory
{
    protected $model = FieldChangeRequest::class;

    /**
     * Default: a `pending` request on the only wired use case (spec 0078) —
     * the `source_id` field of the `request-management` module, whose
     * subject is an `Opportunity` (F-1). `subject_type` is resolved through
     * the morph map ALIAS (`Relation::getMorphAlias()`, strictly enforced by
     * `AppServiceProvider::boot()`), never a raw FQCN — same convention as
     * `RewardFactory::source_type`.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'resource' => 'request-management',
            'subject_type' => Relation::getMorphAlias(Opportunity::class),
            'subject_id' => Opportunity::factory(),
            'field' => 'source_id',
            'current_value' => 1,
            'requested_value' => 2,
            'current_label' => fake()->words(2, true),
            'requested_label' => fake()->words(2, true),
            'reason' => fake()->optional()->sentence(),
            'status' => 'pending',
            'pending_key' => null,
            'requested_by_id' => User::factory(),
            'handled_by_id' => null,
            'handled_at' => null,
            'handling_note' => null,
        ];
    }

    /**
     * `pending_key` (D-5) is `"{subject_type}:{subject_id}:{field}"`, but
     * `subject_id` is only known once the nested `Opportunity::factory()`
     * relation above has been resolved — i.e. after `definition()` returns,
     * not inside it. `afterMaking()` runs on the instantiated model, whose
     * `subject_id` is already the resolved foreign key (same convention as
     * `QuoteFactory::configure()`/`code`).
     */
    public function configure(): static
    {
        return $this->afterMaking(function (FieldChangeRequest $fieldChangeRequest): void {
            if ($fieldChangeRequest->status === 'pending' && $fieldChangeRequest->pending_key === null) {
                $fieldChangeRequest->pending_key = sprintf(
                    '%s:%s:%s',
                    $fieldChangeRequest->subject_type,
                    $fieldChangeRequest->subject_id,
                    $fieldChangeRequest->field,
                );
            }
        });
    }

    /**
     * The request has been approved: `pending_key` is nulled out (D-5) so
     * the UNIQUE constraint never sees a second, still-pending request on
     * the same record+field as a collision.
     */
    public function approved(): static
    {
        return $this->state(fn (): array => [
            'status' => 'approved',
            'pending_key' => null,
            'handled_by_id' => User::factory(),
            'handled_at' => now(),
        ]);
    }

    /**
     * The request has been rejected: the record itself is never touched,
     * only the request's own status/handling columns (D-4).
     */
    public function rejected(): static
    {
        return $this->state(fn (): array => [
            'status' => 'rejected',
            'pending_key' => null,
            'handled_by_id' => User::factory(),
            'handled_at' => now(),
        ]);
    }
}
