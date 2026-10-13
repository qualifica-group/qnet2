<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Spec 0211: the Anagrafiche quick-search finds a registry by the name of a
 * linked referent, word prefix, through this FULLTEXT index (same semantics as
 * spec 0179). FULLTEXT exists only on MySQL/MariaDB: SQLite keeps the LIKE
 * fallback of App\Tables\Shared\WordPrefixMatcher.
 */
return new class extends Migration
{
    private const array SUPPORTED_DRIVERS = ['mysql', 'mariadb'];

    public function up(): void
    {
        if (! $this->supported()) {
            return;
        }

        Schema::table('referents', function (Blueprint $table) {
            $table->fullText(['name'], 'referents_name_fulltext');
        });
    }

    public function down(): void
    {
        if (! $this->supported()) {
            return;
        }

        Schema::table('referents', function (Blueprint $table) {
            $table->dropFullText('referents_name_fulltext');
        });
    }

    private function supported(): bool
    {
        return in_array(DB::getDriverName(), self::SUPPORTED_DRIVERS, true);
    }
};
