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
     * Change property_id from unsignedBigInteger to string(100) to support UUID format from Rightmove
     */
    public function up(): void
    {
        // Disable foreign key checks to allow truncate
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        
        // First clear any existing data to avoid type conversion issues
        DB::table('properties_sold_prices')->truncate();
        DB::table('properties_sold')->truncate();
        
        // Modify the column type
        Schema::table('properties_sold', function (Blueprint $table) {
            $table->string('property_id', 100)->nullable()->change();
        });
        
        // Re-enable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Clear data first
        DB::table('properties_sold_prices')->truncate();
        DB::table('properties_sold')->truncate();
        
        Schema::table('properties_sold', function (Blueprint $table) {
            $table->unsignedBigInteger('property_id')->nullable()->change();
        });
    }
};
