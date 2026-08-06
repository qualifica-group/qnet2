<?php

namespace Database\Factories;

use App\Enums\AttributeContext;
use App\Enums\LayoutFormScope;
use App\Models\AttributeLayout;
use App\Models\ProductCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AttributeLayout>
 */
class AttributeLayoutFactory extends Factory
{
    protected $model = AttributeLayout::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_category_id' => ProductCategory::factory(),
            'context' => AttributeContext::Quote->value,
            // The shared scope is the default configuration (spec 0062 D3 revised).
            'form_mode' => LayoutFormScope::All->value,
            'layout' => ['sections' => []],
        ];
    }

    /**
     * A minimal, contract-valid layout with ONE section holding the given
     * attribute codes, one per row (width `full`) — enough to exercise
     * placement/dedup without hand-building the full shape in every test.
     *
     * @param  array<int, string>  $attributeCodes
     */
    public function withCodes(array $attributeCodes, string $title = 'Section'): static
    {
        return $this->state(fn (): array => [
            'layout' => [
                'sections' => [[
                    'id' => (string) Str::uuid(),
                    'title' => $title,
                    'description' => null,
                    'variant' => 'default',
                    'collapsible' => false,
                    'default_collapsed' => false,
                    'columns' => 1,
                    'sort_order' => 0,
                    'rows' => array_map(
                        static fn (string $code): array => [
                            'id' => (string) Str::uuid(),
                            'items' => [['attribute_code' => $code, 'width' => 'full']],
                        ],
                        $attributeCodes,
                    ),
                ]],
            ],
        ]);
    }
}
