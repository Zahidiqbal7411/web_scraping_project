<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('properties_sold', function (Blueprint $table) {
            if (Schema::hasColumn('properties_sold', 'rightmove_id')) {
                $table->dropColumn('rightmove_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('properties_sold', function (Blueprint $table) {
            $table->string('rightmove_id', 191)->nullable()->after('property_id');
        });
    }
};
