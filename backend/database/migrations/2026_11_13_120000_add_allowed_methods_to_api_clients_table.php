<?php

use App\Enums\ApiClientMethod;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Spec 0392: the HTTP methods each API client may use. Existing clients get
 * every method, so none of them changes behaviour.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('api_clients', function (Blueprint $table) {
            $table->json('allowed_methods')->nullable()->after('rate_limit_per_minute');
        });

        DB::table('api_clients')->update(['allowed_methods' => json_encode(ApiClientMethod::values())]);

        Schema::table('api_clients', function (Blueprint $table) {
            $table->json('allowed_methods')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('api_clients', function (Blueprint $table) {
            $table->dropColumn('allowed_methods');
        });
    }
};
