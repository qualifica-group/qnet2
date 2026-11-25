<?php

declare(strict_types=1);

namespace App\DataObjects\WorkOrders;

/**
 * The own client, company, sites and business-function lines of a project
 * commessa born without an offer (spec 0431, D-2/D-8). Present on
 * CreateWorkOrderData only for that kind of commessa.
 */
final readonly class StandaloneWorkOrderReferences
{
    /**
     * @param  array<int, array{product_category_id: int}>  $productLines
     * @param  array<int, int>  $productIds  D-17: the products in place of offer lines
     */
    public function __construct(
        public int $registryId,
        public int $companyId,
        public ?int $companySiteId,
        public ?int $operationalSiteId,
        public array $productLines,
        public array $productIds = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data  the validated StoreStandaloneProjectWorkOrderRequest payload
     */
    public static function fromValidated(array $data): self
    {
        return new self(
            registryId: (int) $data['registry_id'],
            companyId: (int) $data['company_id'],
            companySiteId: isset($data['company_site_id']) ? (int) $data['company_site_id'] : null,
            operationalSiteId: isset($data['operational_site_id']) ? (int) $data['operational_site_id'] : null,
            productLines: array_values(array_map(
                static fn (array $line): array => ['product_category_id' => (int) $line['product_category_id']],
                (array) $data['product_lines'],
            )),
            productIds: array_values(array_unique(array_map(static fn (mixed $id): int => (int) $id, (array) ($data['product_ids'] ?? [])))),
        );
    }

    /**
     * @return array<string, int|null>
     */
    public function attributes(): array
    {
        return [
            'registry_id' => $this->registryId,
            'company_id' => $this->companyId,
            'company_site_id' => $this->companySiteId,
            'operational_site_id' => $this->operationalSiteId,
        ];
    }
}
