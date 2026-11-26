<?php

/**
 * Child process of AiSpendConcurrencyTest: boots the app on the SQLite FILE named by DB_DATABASE and
 * plays one turn against the cost ledger, so the cap is tried by real, separate processes.
 *
 *   setup                       migrate the needed tables, create 4 people with a message each
 *   reset                       drop every open reservation
 *   reserve <message> <gofile>  announce "ready", wait for the go file, then reserve; prints ok|limit
 */

use App\Models\AiMessage;
use App\Services\AiAssistant\Costs\AiPriceCeiling;
use App\Services\AiAssistant\Costs\AiSpendLedger;
use App\Services\AiAssistant\Costs\AiSpendLimitReached;
use App\Services\AiAssistant\Settings\AiAssistantSettingsService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$mode = $argv[1] ?? '';

if ($mode === 'setup') {
    Artisan::call('migrate', ['--force' => true, '--path' => [
        'database/migrations/2026_06_12_063503_create_activity_log_table.php',
        'database/migrations/2026_11_17_100000_create_ai_conversations_table.php',
        'database/migrations/2026_11_17_100001_create_ai_messages_table.php',
        'database/migrations/2026_12_28_100000_create_ai_assistant_settings_table.php',
        'database/migrations/2026_12_30_100000_create_ai_cost_tables.php',
        'database/migrations/2026_12_30_100001_add_business_function_ids_to_ai_messages.php',
    ]]);
    app(AiAssistantSettingsService::class)->current()->update(['spend_cap_person_usd' => '3.5', 'spend_cap_total_usd' => '3.5']);
    foreach ([1, 2, 3, 4] as $id) {
        DB::table('ai_conversations')->insert(['id' => $id, 'user_id' => $id, 'title' => 't', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('ai_messages')->insert(['id' => $id, 'ai_conversation_id' => $id, 'role' => 'user', 'content' => 'x', 'created_at' => now(), 'updated_at' => now()]);
    }
    echo 'done';
} elseif ($mode === 'reset') {
    DB::table('ai_cost_reservations')->delete();
    echo 'done';
} elseif ($mode === 'reserve') {
    touch($argv[3].'.ready'.$argv[2].'.'.getmypid());
    $deadline = microtime(true) + 30;
    while (! file_exists($argv[3]) && microtime(true) < $deadline) {
        usleep(1000);
    }
    try {
        // 0.001 $ per token: 1000 bytes + 1000 margin + 500 output tokens bound to 3 $, so the 3.5 $ cap fits one
        app(AiSpendLedger::class)->reserve(AiMessage::query()->findOrFail((int) $argv[2]), 1000, 500, new AiPriceCeiling('0.001', '0.002'));
        echo 'ok';
    } catch (AiSpendLimitReached) {
        echo 'limit';
    }
}
