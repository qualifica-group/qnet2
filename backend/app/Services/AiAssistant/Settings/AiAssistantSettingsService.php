<?php

declare(strict_types=1);

namespace App\Services\AiAssistant\Settings;

use App\Models\AiAssistantSetting;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Reads and saves the single settings row of the assistant (spec 0433).
 * `effective()` is what the assistant turn calls to learn model, routing,
 * additional instructions and the general switch.
 */
class AiAssistantSettingsService
{
    public function __construct(private readonly OpenRouterCatalog $catalog) {}

    /**
     * The settings in force; the row is created with the initial values on first use.
     */
    public function effective(): AiEffectiveSettings
    {
        $setting = $this->current();

        return new AiEffectiveSettings(
            enabled: $setting->enabled,
            modelId: $setting->model_id,
            instructions: $setting->instructions,
            routing: $setting->routing(),
            spendCapPersonUsd: $setting->spend_cap_person_usd,
            spendCapTotalUsd: $setting->spend_cap_total_usd,
        );
    }

    public function current(): AiAssistantSetting
    {
        $setting = AiAssistantSetting::query()->with('updatedBy')->orderBy('id')->first();

        return $setting ?? AiAssistantSetting::query()->create($this->initialValues())->load('updatedBy');
    }

    /**
     * @param  array<string, mixed>  $data  validated payload: only the keys present are changed
     *
     * @throws ValidationException when the model is not in the filtered OpenRouter list
     * @throws OpenRouterCatalogUnavailableException when OpenRouter cannot confirm the model
     */
    public function update(array $data, User $actor): AiAssistantSetting
    {
        $setting = $this->current();

        // Step 1: a changed model must be one the assistant can use (a saved one is kept as is,
        // so the switch still works while OpenRouter is down)
        if (isset($data['model_id']) && $data['model_id'] !== $setting->model_id) {
            $this->assertModelAvailable($data['model_id']);
        }

        // Step 2: apply the changes and stamp the author
        $setting->fill($this->columns($data));
        $setting->updated_by_id = $actor->id;
        $setting->save();

        return $setting->load('updatedBy');
    }

    private function assertModelAvailable(string $modelId): void
    {
        if (! $this->catalog->has($modelId)) {
            throw ValidationException::withMessages(['model_id' => __('The selected model is not available.')]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function columns(array $data): array
    {
        $columns = array_intersect_key($data, array_flip([
            'enabled', 'model_id', 'instructions', 'spend_cap_person_usd', 'spend_cap_total_usd',
        ]));

        if (array_key_exists('instructions', $columns)) {
            $columns['instructions'] ??= '';
        }

        foreach ($data['routing'] ?? [] as $key => $value) {
            $columns['routing_'.$key] = $value;
        }

        return $columns;
    }

    /**
     * @return array<string, mixed>
     */
    private function initialValues(): array
    {
        $routing = config('ai-assistant.settings.default_routing');

        return [
            'enabled' => true,
            'model_id' => config('ai-assistant.settings.default_model'),
            'instructions' => AiDefaultInstructions::TEXT,
            'routing_zdr' => $routing['zdr'],
            'routing_data_collection' => $routing['data_collection'],
            'routing_require_parameters' => $routing['require_parameters'],
            'routing_sort' => $routing['sort'],
            'spend_cap_total_usd' => config('ai-assistant.settings.default_spend_cap_total_usd'),
            'spend_cap_person_usd' => config('ai-assistant.settings.default_spend_cap_person_usd'),
        ];
    }
}
