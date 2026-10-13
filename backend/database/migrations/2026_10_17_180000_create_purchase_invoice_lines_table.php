<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Passive invoice phase 2 (spec 0250): lines with cost type and work order
 * (D-5, D-6), `document_year` and the unique supplier document number per
 * year (D-7). Existing documents get one line built from their header and the
 * year of their invoices, so every document has lines.
 */
return new class extends Migration
{
    private const int CHUNK = 200;

    public function up(): void
    {
        // Step 1: the lines table
        Schema::create('purchase_invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_invoice_id')->constrained('purchase_invoices')->cascadeOnDelete();
            $table->foreignId('purchase_order_line_id')->nullable()->constrained('purchase_order_lines')->restrictOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('description', 500);
            $table->decimal('quantity', 12, 3);
            $table->decimal('unit_price', 14, 2);
            $table->foreignId('vat_rate_id')->nullable()->constrained('vat_rates')->nullOnDelete();
            $table->foreignId('cost_type_id')->constrained('cost_types')->restrictOnDelete();
            $table->foreignId('work_order_id')->nullable()->constrained('work_orders')->nullOnDelete();
            $table->decimal('net_amount', 15, 2);
            $table->decimal('vat_amount', 15, 2);
            $table->decimal('total_amount', 15, 2);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            // D-8 backstop: an order line is billed once (several NULLs stay allowed)
            $table->unique('purchase_order_line_id');
        });

        // Step 2: the year column and the unique supplier number (NULL year = not constrained)
        Schema::table('purchase_invoices', function (Blueprint $table) {
            $table->unsignedSmallInteger('document_year')->nullable()->after('document_date');
            $table->unique(['supplier_id', 'document_number', 'document_year'], 'purchase_invoices_supplier_number_year_unique');
        });

        // Step 3: data
        $this->backfillYears();
        $this->backfillLines();
    }

    public function down(): void
    {
        Schema::table('purchase_invoices', function (Blueprint $table) {
            $table->dropUnique('purchase_invoices_supplier_number_year_unique');
        });
        Schema::table('purchase_invoices', function (Blueprint $table) {
            $table->dropColumn('document_year');
        });
        Schema::dropIfExists('purchase_invoice_lines');
    }

    /**
     * Invoices only; a duplicated (supplier, number, year) keeps the year on
     * the oldest document and leaves the others unconstrained instead of
     * failing the migration.
     */
    private function backfillYears(): void
    {
        $taken = [];

        DB::table('purchase_invoices')->where('document_type', 'invoice')->orderBy('id')
            ->chunkById(self::CHUNK, function ($rows) use (&$taken): void {
                foreach ($rows as $row) {
                    $year = (int) substr((string) $row->document_date, 0, 4);
                    $key = $row->supplier_id.'|'.$row->document_number.'|'.$year;

                    if ($row->document_number !== null && isset($taken[$key])) {
                        continue;
                    }

                    $taken[$key] = true;
                    DB::table('purchase_invoices')->where('id', $row->id)->update(['document_year' => $year]);
                }
            });
    }

    private function backfillLines(): void
    {
        $fallbackCostTypeId = DB::table('cost_types')->orderBy('is_active', 'desc')->orderBy('sort_order')->orderBy('id')->value('id');
        $now = now();

        DB::table('purchase_invoices')
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('purchase_invoice_lines')->whereColumn('purchase_invoice_lines.purchase_invoice_id', 'purchase_invoices.id'))
            ->orderBy('id')
            ->chunkById(self::CHUNK, function ($documents) use ($fallbackCostTypeId, $now): void {
                $costTypeFor = fn ($document) => $document->cost_type_id ?? $fallbackCostTypeId;

                if ($documents->contains(fn ($document) => $costTypeFor($document) === null)) {
                    throw new RuntimeException('Cannot backfill the purchase invoice lines: no cost type exists.');
                }

                DB::table('purchase_invoice_lines')->insert($documents->map(fn ($document): array => [
                    'purchase_invoice_id' => $document->id,
                    'description' => trim('Documento '.($document->document_number ?? '')),
                    'quantity' => '1.000',
                    'unit_price' => $document->net_amount,
                    'cost_type_id' => $costTypeFor($document),
                    'work_order_id' => $document->work_order_id,
                    'net_amount' => $document->net_amount,
                    'vat_amount' => $document->vat_amount,
                    'total_amount' => $document->total_amount,
                    'sort_order' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all());
            });
    }
};
