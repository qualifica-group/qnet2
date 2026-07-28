<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cities', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('country_id');
            $table->unsignedBigInteger('state_id');

            // The level between state/region and city. Nullable: many countries
            // have no province level, and those cities stay reachable through
            // state_id / country_id.
            $table->unsignedBigInteger('province_id')->nullable();

            $table->string('name');
            $table->string('country_code', 2);
            $table->timestamps();

            $table->foreign('country_id')
                ->references('id')
                ->on('countries')
                ->onDelete('cascade');

            $table->foreign('state_id')
                ->references('id')
                ->on('states')
                ->onDelete('cascade');

            $table->foreign('province_id')
                ->references('id')
                ->on('provinces')
                ->onDelete('cascade');

            $table->index('province_id');

            // Supports the geo cascade city search (`name LIKE` + order by name).
            $table->index('name', 'cities_name_index');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('cities');
    }
};
