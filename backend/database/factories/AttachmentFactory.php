<?php

namespace Database\Factories;

use App\Models\Attachment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attachment>
 */
class AttachmentFactory extends Factory
{
    protected $model = Attachment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->word().'.pdf';

        return [
            'collection' => null,
            'disk' => 'local',
            'path' => 'attachments/'.$this->faker->uuid().'.pdf',
            'original_name' => $name,
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'size' => $this->faker->numberBetween(1024, 1048576),
            'uploaded_by' => User::factory(),
        ];
    }

    /**
     * A link-only legacy document (spec 0295 D-10) under the given base URL.
     */
    public function legacyLink(string $baseUrl = 'https://legacy.test', string $source = 'orderdocuments'): static
    {
        return $this->state(function () use ($baseUrl, $source): array {
            $oldId = $this->faker->unique()->numberBetween(1, 1000000);

            return [
                'collection' => 'documents',
                'disk' => Attachment::LEGACY_DISK,
                'path' => "{$source}/{$oldId}",
                'external_url' => rtrim($baseUrl, '/')."/document/{$oldId}/file.pdf",
                'old_source' => $source,
                'old_id' => $oldId,
            ];
        });
    }
}
