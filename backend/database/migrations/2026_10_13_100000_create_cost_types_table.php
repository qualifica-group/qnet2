<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cost types, a configurable lookup classifying purchase order lines (spec
 * 0210, D-3). The four initial values are reference data, seeded here.
 */
return new class extends Migration
{
    private const array INITIAL_NAMES = ['Collaboratori', 'Fitti', 'Utenze', 'Altro'];

    public function up(): void
    {
        Schema::create('cost_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('description', 500)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $now = now();

        DB::table('cost_types')->insert(array_map(
            static fn (string $name, int $index): array => [
                'name' => $name,
                'sort_order' => $index + 1,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            self::INITIAL_NAMES,
            array_keys(self::INITIAL_NAMES),
        ));
    }

    public function down(): void
    {
        Schema::dropIfExists('cost_types');
    }
};
