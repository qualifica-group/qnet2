<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Spec 0254, D-11: every fulfilled order gets one generated receipt with the
 * ordered quantities, so received_quantity matches the legacy status.
 * Partially fulfilled orders stay without receipts.
 */
return new class extends Migration
{
    private const string NOTE = 'Ricezione generata dalla migrazione (spec 0254)';

    public function up(): void
    {
        $now = now();

        DB::table('purchase_orders')->where('status', 'fulfilled')->orderBy('id')->each(function ($order) use ($now): void {
            $lines = DB::table('purchase_order_lines')->where('purchase_order_id', $order->id)->orderBy('id')->get();

            if ($lines->isEmpty()) {
                return;
            }

            $receiptId = DB::table('purchase_order_receipts')->insertGetId([
                'purchase_order_id' => $order->id,
                'receipt_date' => substr((string) $order->updated_at, 0, 10),
                'notes' => self::NOTE,
                'created_by' => $order->created_by,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('purchase_order_receipt_lines')->insert($lines->map(fn ($line): array => [
                'purchase_order_receipt_id' => $receiptId,
                'purchase_order_line_id' => $line->id,
                'quantity' => $line->quantity,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all());

            DB::table('purchase_order_lines')->where('purchase_order_id', $order->id)->update(['received_quantity' => DB::raw('quantity')]);
        });
    }

    public function down(): void
    {
        $receiptIds = DB::table('purchase_order_receipts')->where('notes', self::NOTE)->pluck('id');

        $lineIds = DB::table('purchase_order_receipt_lines')->whereIn('purchase_order_receipt_id', $receiptIds)->pluck('purchase_order_line_id');
        DB::table('purchase_order_lines')->whereIn('id', $lineIds)->update(['received_quantity' => 0]);
        DB::table('purchase_order_receipt_lines')->whereIn('purchase_order_receipt_id', $receiptIds)->delete();
        DB::table('purchase_order_receipts')->whereIn('id', $receiptIds)->delete();
    }
};
