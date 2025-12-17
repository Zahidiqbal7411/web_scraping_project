<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Change property_id back to unsignedBigInteger to match the properties table's numeric ID format
     */
    public function up(): void
    {
        // Disable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        
        // Clear existing data (which has UUID format) to avoid conversion issues
        DB::table('properties_sold_prices')->truncate();
        DB::table('properties_sold')->truncate();
        
        // Change property_id from string back to unsignedBigInteger
        Schema::table('properties_sold', function (Blueprint $table) {
            $table->unsignedBigInteger('property_id')->nullable()->change();
        });
        
        // Re-enable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        
        DB::table('properties_sold_prices')->truncate();
        DB::table('properties_sold')->truncate();
        
        Schema::table('properties_sold', function (Blueprint $table) {
            $table->string('property_id', 100)->nullable()->change();
        });
        
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
