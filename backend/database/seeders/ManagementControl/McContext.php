<?php

namespace Database\Seeders\ManagementControl;

use App\Models\Product;
use App\Models\Referent;
use App\Models\Registry;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Everything the dataset steps share, resolved once by McDirectory: the actor, the
 * selling companies with their site and bank, the registries, the agents, the
 * products and the lookups, all by name.
 */
final class McContext
{
    /**
     * @param  list<array{company_id: int, company_site_id: int|null, bank_id: int}>  $companies  the selling companies
     * @param  list<Registry>  $customers
     * @param  array<string, Registry>  $suppliers  by name
     * @param  list<Referent>  $agents
     * @param  list<Referent>  $reporters
     * @param  array<string, Product>  $products  by name
     * @param  array<string, array{category_id: int, business_function_id: int}>  $categories  by category name
     * @param  array<string, int>  $costTypes  by name
     * @param  list<int>  $customerPaymentMethodIds
     * @param  Collection<int, User>  $supervisors  users who lead the commesse
     * @param  array<int, bool>  $latePayers  customer registry id => pays late
     */
    public function __construct(
        public readonly User $actor,
        public readonly array $companies,
        public readonly array $customers,
        public readonly array $suppliers,
        public readonly array $agents,
        public readonly array $reporters,
        public readonly array $products,
        public readonly array $categories,
        public readonly array $costTypes,
        public readonly array $customerPaymentMethodIds,
        public readonly int $supplierPaymentMethodId,
        public readonly int $vatRateId,
        public readonly Collection $supervisors,
        public readonly array $latePayers,
    ) {}

    /** The bank account of the selling company $companyId. */
    public function bankOf(int $companyId): int
    {
        foreach ($this->companies as $company) {
            if ($company['company_id'] === $companyId) {
                return $company['bank_id'];
            }
        }

        return $this->companies[0]['bank_id'];
    }
}
