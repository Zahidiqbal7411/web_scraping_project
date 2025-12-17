<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Remove linked_property_id column - we now use source_sold_link for matching
     */
    public function up(): void
    {
        Schema::table('properties_sold', function (Blueprint $table) {
            if (Schema::hasColumn('properties_sold', 'linked_property_id')) {
                $table->dropColumn('linked_property_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('properties_sold', function (Blueprint $table) {
            $table->unsignedBigInteger('linked_property_id')->nullable()->after('property_id');
        });
    }
};
