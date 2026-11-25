<?php

declare(strict_types=1);

use App\Enums\AttributeContext;
use App\Models\Attribute;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\QuoteLine;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Http;
use Integrations\Formalab\FormalabServiceProvider;
use Integrations\Formalab\Models\FormalabCallLog;
use Integrations\Formalab\Models\FormalabCategorySetting;
use Integrations\Formalab\Models\FormalabClass;
use Integrations\Formalab\Models\FormalabSyncRun;
use Integrations\Formalab\Services\CatalogSyncLauncher;
use Integrations\Formalab\Services\ClassCatalogSync;
use Integrations\Formalab\Support\FormalabField;
use Integrations\Formalab\Support\Trigger;

if (! function_exists('formalabEnable')) {
    /**
     * Switches the integration on for the current test: the provider reads the
     * flag at boot, so it is registered again once the config is set.
     *
     * @param  array<string, mixed>  $overrides  config keys (without the `formalab.` prefix)
     */
    function formalabEnable(array $overrides = []): void
    {
        $settings = array_merge([
            'enabled' => true,
            'url' => 'https://formalab.test',
            'user_email' => 'qnet@formalab.test',
            'user_password' => 'p4ss-never-logged',
            'inbound_token' => 'inbound-secret-token',
        ], $overrides);

        foreach ($settings as $key => $value) {
            config(["formalab.{$key}" => $value]);
        }

        app()->register(FormalabServiceProvider::class, force: true);

        // commands() queues onto the console application: rebuild it so the new commands are known
        app(ConsoleKernel::class)->setArtisan(null);
    }
}

if (! function_exists('formalabSync')) {
    /**
     * A manual catalogue sync run inline, opened through the launcher like the console command.
     */
    function formalabSync(): FormalabSyncRun
    {
        $run = app(CatalogSyncLauncher::class)->open(Trigger::Manual);

        return app(ClassCatalogSync::class)->handle($run);
    }
}

if (! function_exists('formalabCallLog')) {
    /**
     * A call log row, optionally back-dated (created_at is not mass-assignable).
     *
     * @param  array<string, mixed>  $attributes
     */
    function formalabCallLog(array $attributes = [], ?DateTimeInterface $at = null): FormalabCallLog
    {
        $log = new FormalabCallLog(array_merge([
            'direction' => 'outbound', 'method' => 'GET', 'endpoint' => '/x', 'outcome' => 'success', 'status_code' => 200, 'trigger' => 'schedule',
        ], $attributes));

        if ($at !== null) {
            $log->created_at = $at;
        }

        $log->save();

        return $log;
    }
}

if (! function_exists('formalabSuperAdmin')) {
    function formalabSuperAdmin(): User
    {
        Role::query()->firstOrCreate(['name' => 'super-admin']);
        $user = User::factory()->create();
        $user->assignRole('super-admin');

        return $user;
    }
}

if (! function_exists('formalabClassPayload')) {
    /**
     * A class as Formalab returns it (GET /api/classi), trailing spaces included.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    function formalabClassPayload(int $id, array $overrides = []): array
    {
        return array_replace_recursive([
            'id' => $id,
            'codice' => "COD-{$id}  ",
            'start_dt' => '2026-03-02',
            'end_dt' => '2026-06-30',
            'data_esame' => '',
            'data_ip_incasso' => '',
            'anticipo_data_accredito' => '',
            'anticipo_num_decreto_data' => '',
            'saldo_data_accredito' => '',
            'saldo_num_decreto_data' => '',
            'num_discenti' => 12,
            'sede_parte_teorica' => ['denominazione' => 'Sede Nord', 'city' => 'Milano', 'address' => 'Via Roma 1', 'codice_sede' => 'S1'],
            'corso' => ['title' => "Course {$id} ", 'abbreviated_title' => 'C', 'total_hours' => 300, 'codice_corso' => 'CC'],
            'stato' => ['name' => 'Avviata'],
            'stato_corso' => ['name' => 'In corso'],
            'discenti' => [],
        ], $overrides);
    }
}

if (! function_exists('formalabFakeApi')) {
    /**
     * Fakes the whole Formalab API over a mutable class list (login, paged
     * catalogue, single class). Mutate `$catalog['classes']` to change what
     * Formalab answers; no real network is ever reached.
     *
     * @param  list<array<string, mixed>>  $classes
     */
    function formalabFakeApi(array $classes): ArrayObject
    {
        $catalog = new ArrayObject(['classes' => $classes]);

        Http::preventStrayRequests();
        Http::fake(function ($request) use ($catalog) {
            $path = parse_url($request->url(), PHP_URL_PATH);

            if ($path === '/api/login') {
                return Http::response(['accessToken' => 'token-from-login']);
            }

            if (preg_match('#^/api/classi/(\d+)$#', $path, $match) === 1) {
                $class = collect($catalog['classes'])->firstWhere('id', (int) $match[1]);

                return $class === null ? Http::response(['message' => 'Not found'], 404) : Http::response(['classe' => $class]);
            }

            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            $perPage = (int) ($query['per_page'] ?? 100);
            $page = (int) ($query['page'] ?? 1);
            $all = $catalog['classes'];

            return Http::response([
                'classi' => array_slice($all, ($page - 1) * $perPage, $perPage),
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => max(1, (int) ceil(count($all) / $perPage)),
                'total' => count($all),
            ]);
        });

        return $catalog;
    }
}

if (! function_exists('formalabCourseAttributes')) {
    /**
     * The 12 course field attributes plus `gol_notice` (not a course field),
     * reused when they already exist. REQUIREMENT CHANGED (spec 0426):
     * `gol_corso` is the class select (relation onto the Formalab classes).
     *
     * @return array<string, Attribute> code => attribute
     */
    function formalabCourseAttributes(): array
    {
        $types = [
            'gol_corso' => 'relation', 'gol_stato_corso' => 'text', 'gol_stato_classe' => 'text',
            'gol_data_inizio_corso' => 'date', 'gol_data_fine_corso' => 'date', 'gol_ore_totali_corso' => 'integer',
            'gol_data_esame' => 'date', 'gol_data_ip_incasso' => 'date', 'gol_anticipo_decreto_numero_data' => 'text',
            'gol_anticipo_data_accredito' => 'date', 'gol_saldo_decreto_numero_data' => 'text', 'gol_saldo_data_accredito' => 'date',
            'gol_notice' => 'text',
        ];

        return collect($types)->map(function (string $type, string $code): Attribute {
            $attribute = Attribute::query()->where('code', $code)->first() ?? Attribute::factory()->create(['code' => $code, 'type' => $type === 'relation' ? 'text' : $type]);

            return $type === 'relation' ? formalabClassSelectAttribute($attribute) : $attribute;
        })->all();
    }
}

if (! function_exists('formalabClassSelectAttribute')) {
    /**
     * Turns an attribute into the work order class select: a relation onto the
     * Formalab classes, whose value is the edition id (spec 0426 D-3).
     */
    function formalabClassSelectAttribute(Attribute $attribute): Attribute
    {
        $attribute->forceFill(['type' => 'relation', 'relation_target' => [
            'entity_type' => FormalabClass::ENTITY_TYPE, 'cardinality' => 'one', 'for_select_resource' => FormalabClass::FOR_SELECT_RESOURCE,
        ]])->save();

        return $attribute;
    }
}

if (! function_exists('formalabSuggestedMap')) {
    /**
     * The 12 course fields of the first release as a category field map
     * (spec 0423 D-5 suggestions).
     *
     * @return list<array{formalab_field: string, attribute_code: string}>
     */
    function formalabSuggestedMap(): array
    {
        return collect(FormalabField::cases())
            ->filter(fn (FormalabField $field): bool => $field->suggestedAttributeCode() !== null)
            ->map(fn (FormalabField $field): array => ['formalab_field' => $field->value, 'attribute_code' => $field->suggestedAttributeCode()])
            ->values()
            ->all();
    }
}

if (! function_exists('formalabLinkCategory')) {
    /**
     * Stores the Formalab settings of a category, as the PUT endpoint would.
     *
     * @param  list<array{formalab_field: string, attribute_code: string}>|null  $map
     */
    function formalabLinkCategory(ProductCategory $category, ?array $map, bool $enabled = true): FormalabCategorySetting
    {
        return FormalabCategorySetting::query()->updateOrCreate(
            ['product_category_id' => $category->id],
            ['enabled' => $enabled, 'field_map' => $map],
        );
    }
}

if (! function_exists('formalabGolWorkOrder')) {
    /**
     * A work order whose product category carries the 12 course fields plus
     * `gol_notice` (not a course field). By default the category is linked to
     * Formalab with the suggested map (spec 0423), so the work order is eligible.
     *
     * @param  array<string, mixed>  $values  initial attribute_values
     */
    function formalabGolWorkOrder(array $values = [], bool $linkCategory = true): WorkOrder
    {
        $category = ProductCategory::factory()->create();
        $position = 0;

        foreach (formalabCourseAttributes() as $attribute) {
            $category->attributes()->attach($attribute->id, [
                'is_required' => false, 'sort_order' => $position++, 'context' => AttributeContext::WorkOrder->value,
            ]);
        }

        if ($linkCategory) {
            formalabLinkCategory($category, formalabSuggestedMap());
        }

        $line = QuoteLine::factory()->create(['product_id' => Product::factory()->create(['category_id' => $category->id])->id]);
        $workOrder = WorkOrder::factory()->create(['quote_id' => $line->quote_id]);
        $workOrder->quoteLines()->attach($line->id);

        if ($values !== []) {
            $workOrder->forceFill(['attribute_values' => $values])->save();
        }

        return $workOrder->refresh();
    }
}
