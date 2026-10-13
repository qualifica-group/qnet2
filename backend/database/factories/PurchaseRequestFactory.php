<?php

namespace Database\Factories;

use App\Enums\PurchaseRequestStatus;
use App\Models\BusinessFunction;
use App\Models\Company;
use App\Models\CompanySite;
use App\Models\OperationalSite;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestPriority;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseRequest>
 */
class PurchaseRequestFactory extends Factory
{
    protected $model = PurchaseRequest::class;

    /**
     * Header only; lines are added through PurchaseRequestLineFactory (the
     * totals stay at zero until the service or the test computes them).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'subject' => fake()->sentence(4),
            'requested_at' => now()->toDateString(),
            'purchase_request_priority_id' => fn (): int => PurchaseRequestPriority::query()->where('is_default', true)->value('id') ?? PurchaseRequestPriority::factory()->default()->create()->id,
            'requester_id' => User::factory(),
            'function_manager_id' => User::factory(),
            'company_id' => Company::factory(),
            'company_site_id' => fn (array $attributes): int => CompanySite::factory()->create(['company_id' => $attributes['company_id']])->id,
            'operational_site_id' => OperationalSite::factory(),
            'business_function_id' => BusinessFunction::factory(),
            'created_by' => fn (array $attributes): int => $attributes['requester_id'],
            'status' => PurchaseRequestStatus::Open,
        ];
    }

    public function closed(?User $by = null): static
    {
        return $this->state(fn (): array => [
            'status' => PurchaseRequestStatus::Closed,
            'closed_by' => $by?->id ?? User::factory(),
            'closed_at' => now(),
        ]);
    }
}
