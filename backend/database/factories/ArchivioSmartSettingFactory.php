<?php

namespace Database\Factories;

use App\Models\ArchivioSmartSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ArchivioSmartSetting>
 */
class ArchivioSmartSettingFactory extends Factory
{
    protected $model = ArchivioSmartSetting::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'base_url' => 'https://archivio.test/api/v1',
            'token' => 'as_test_token_123456',
        ];
    }
}
