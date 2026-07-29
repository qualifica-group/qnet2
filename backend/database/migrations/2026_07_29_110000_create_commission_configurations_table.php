<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commission_configurations', function (Blueprint $table) {
            $table->id();
            $table->string('name', 191);
            $table->string('recipient_role', 32);
            $table->string('application_scope', 32);
            $table->foreignId('product_category_id')->nullable()->constrained('product_categories')->restrictOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->restrictOnDelete();
            $table->string('commission_type', 32);
            $table->decimal('value', 15, 4);
            $table->integer('priority')->default(0);
            $table->date('valid_from');
            $table->date('valid_until')->nullable();
            $table->string('status', 16);
            $table->text('internal_note')->nullable();
            $table->timestamps();

            $table->index(
                ['recipient_role', 'application_scope', 'status', 'product_id', 'priority', 'valid_from', 'valid_until', 'id'],
                'commission_config_product_resolution_index',
            );
            $table->index(
                ['recipient_role', 'application_scope', 'status', 'product_category_id', 'priority', 'valid_from', 'valid_until', 'id'],
                'commission_config_category_resolution_index',
            );
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE commission_configurations ADD CONSTRAINT commission_configurations_scope_check CHECK ((application_scope = 'PRODUCT' AND product_id IS NOT NULL AND product_category_id IS NULL) OR (application_scope = 'PRODUCT_CATEGORY' AND product_category_id IS NOT NULL AND product_id IS NULL))");
            DB::statement('ALTER TABLE commission_configurations ADD CONSTRAINT commission_configurations_value_check CHECK (value >= 0)');
            DB::statement('ALTER TABLE commission_configurations ADD CONSTRAINT commission_configurations_validity_check CHECK (valid_until IS NULL OR valid_until >= valid_from)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_configurations');
    }
};
