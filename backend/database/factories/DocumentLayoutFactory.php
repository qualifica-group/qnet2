<?php

namespace Database\Factories;

use App\Enums\DocumentLayoutModule;
use App\Models\DocumentLayout;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentLayout>
 */
class DocumentLayoutFactory extends Factory
{
    protected $model = DocumentLayout::class;

    /** Incrementing counter backing `code`'s uniqueness (regex: ^[a-z][a-z0-9_]*$). */
    private static int $nextCodeSuffix = 1;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(3, true),
            'code' => 'layout_'.self::$nextCodeSuffix++,
            'description' => fake()->optional()->sentence(),
            'module' => DocumentLayoutModule::Quotes->value,
            'is_active' => true,
            'is_default' => false,
            'config' => self::minimalConfig(),
        ];
    }

    /**
     * A structurally valid `config` (accepted by DocumentLayoutConfigValidator):
     * one page block per zone type is deliberately NOT required — header and
     * footer are empty, body carries a single plain text block, matching the
     * smallest tree a real layout would ever have.
     *
     * @return array<string, mixed>
     */
    public static function minimalConfig(): array
    {
        return [
            'version' => 1,
            'page' => [
                'format' => 'A4',
                'orientation' => 'portrait',
                'margins' => ['top' => 1134, 'right' => 1134, 'bottom' => 1134, 'left' => 1134],
                'default_font' => ['family' => 'Arial', 'size' => 11, 'color' => '000000'],
            ],
            'header' => ['blocks' => []],
            'body' => [
                'blocks' => [
                    [
                        'id' => 'block-1',
                        'type' => 'text',
                        'align' => 'left',
                        'space_before' => 0,
                        'space_after' => 0,
                        'line_height' => 1.0,
                        'runs' => [
                            [
                                'text' => 'Sample text',
                                'field' => null,
                                'bold' => false,
                                'italic' => false,
                                'underline' => false,
                                'font' => null,
                                'size' => null,
                                'color' => null,
                            ],
                        ],
                    ],
                ],
            ],
            'footer' => ['blocks' => []],
        ];
    }
}
