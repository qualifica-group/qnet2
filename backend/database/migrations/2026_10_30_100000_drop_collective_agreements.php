<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/**
 * Spec 0293: the CCNL module goes, data included (D-1). The contract level
 * column and both catalogue tables are dropped, then the state saved under the
 * `collective-agreements` domain. Role pivots cascade from `permissions`.
 * Activity of the contract conditions stays as history (D-3); `down` brings
 * back the empty structure only.
 */
return new class extends Migration
{
    private const string DOMAIN = 'collective-agreements';

    private const array SUBJECT_TYPES = ['collective_agreement', 'collective_agreement_level'];

    private const string LEVEL_FOREIGN = 'employment_contracts_level_foreign';

    public function up(): void
    {
        Schema::table('employment_contracts', function (Blueprint $table): void {
            // SQLite finds a foreign key by its columns only; MySQL by the custom name it was created with.
            $table->dropForeign(DB::getDriverName() === 'sqlite' ? ['collective_agreement_level_id'] : self::LEVEL_FOREIGN);
            $table->dropColumn('collective_agreement_level_id');
        });

        Schema::dropIfExists('collective_agreement_levels');
        Schema::dropIfExists('collective_agreements');

        DB::table('permissions')->where('name', 'like', self::DOMAIN.'.%')->delete();
        DB::table('role_field_permissions')->where('resource', self::DOMAIN)->delete();
        DB::table('user_table_preferences')->where('domain', self::DOMAIN)->delete();
        DB::table('user_table_filters')->where('domain', self::DOMAIN)->delete();
        DB::table('table_filter_views')->where('domain', self::DOMAIN)->delete();
        DB::table('activity_log')->whereIn('subject_type', self::SUBJECT_TYPES)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::create('collective_agreements', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 191)->unique();
            $table->string('code', 50)->nullable();
            $table->timestamps();
        });

        Schema::create('collective_agreement_levels', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('collective_agreement_id')->constrained()->cascadeOnDelete();
            $table->string('code', 50);
            $table->string('description', 191)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['collective_agreement_id', 'code'], 'ca_levels_agreement_code_unique');
        });

        Schema::table('employment_contracts', function (Blueprint $table): void {
            $table->foreignId('collective_agreement_level_id')->nullable()->after('company_id')
                ->constrained('collective_agreement_levels', indexName: self::LEVEL_FOREIGN)
                ->restrictOnDelete();
        });
    }
};
