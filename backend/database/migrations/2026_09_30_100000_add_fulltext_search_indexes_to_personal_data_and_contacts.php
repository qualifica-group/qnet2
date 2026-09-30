<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Spec 0179: the quick-search of Gestione Richieste / Gestione Iscritti matches
 * the client's card and primary contacts by word prefix through these FULLTEXT
 * indexes instead of `LIKE '%term%'` (30-120 s on 1M rows, stress test
 * 2026-09-29). FULLTEXT exists only on MySQL/MariaDB: the SQLite test/dev
 * database keeps the LIKE fallback of App\Tables\RequestManagement\RequestClientSearch.
 */
return new class extends Migration
{
    private const array SUPPORTED_DRIVERS = ['mysql', 'mariadb'];

    public function up(): void
    {
        if (! $this->supported()) {
            return;
        }

        Schema::table('personal_data', function (Blueprint $table) {
            $table->fullText(['first_name', 'last_name', 'tax_code', 'vat_number'], 'personal_data_search_fulltext');
        });

        Schema::table('contacts', function (Blueprint $table) {
            $table->fullText(['value'], 'contacts_value_fulltext');
        });
    }

    public function down(): void
    {
        if (! $this->supported()) {
            return;
        }

        Schema::table('personal_data', function (Blueprint $table) {
            $table->dropFullText('personal_data_search_fulltext');
        });

        Schema::table('contacts', function (Blueprint $table) {
            $table->dropFullText('contacts_value_fulltext');
        });
    }

    private function supported(): bool
    {
        return in_array(DB::getDriverName(), self::SUPPORTED_DRIVERS, true);
    }
};
