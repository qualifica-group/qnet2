<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Spec 0050 D-6: the impersonation audit entries were logged without an
 * event, so the activity timeline showed them with no label. The entries
 * now carry event = description; this aligns the rows written before.
 */
return new class extends Migration
{
    private const array EVENTS = ['impersonation.started', 'impersonation.stopped'];

    public function up(): void
    {
        foreach (self::EVENTS as $event) {
            DB::table(config('activitylog.table_name'))
                ->whereNull('event')
                ->where('description', $event)
                ->update(['event' => $event]);
        }
    }

    public function down(): void
    {
        DB::table(config('activitylog.table_name'))
            ->whereIn('event', self::EVENTS)
            ->update(['event' => null]);
    }
};
