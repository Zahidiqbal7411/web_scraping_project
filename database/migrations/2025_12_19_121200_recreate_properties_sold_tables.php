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
     * Fix properties_sold schema to properly link to parent properties
     */
    public function up(): void
    {
        // Disable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        
        // Drop and recreate properties_sold table with correct schema
        Schema::dropIfExists('properties_sold_prices');
        Schema::dropIfExists('properties_sold');
        
        Schema::create('properties_sold', function (Blueprint $table) {
            $table->id(); // Primary autoincrement ID
            $table->unsignedBigInteger('property_id'); // Link to parent property (NOT unique, multiple sold props per parent)
            $table->text('source_sold_link')->nullable(); // The sold prices page URL this came from
            $table->text('detail_url')->nullable(); // Individual sold property detail URL
            $table->string('location')->nullable();
            $table->string('property_type')->nullable();
            $table->string('bedrooms')->nullable();
            $table->string('bathrooms')->nullable();
            $table->string('tenure')->nullable();
            $table->timestamps();
            
            // Indexes for performance
            $table->index('property_id');
            // Composite index for uniqueness check in updateOrCreate
            $table->index(['property_id', 'location']);
        });
        
        Schema::create('properties_sold_prices', function (Blueprint $table) {
            $table->id(); // Primary autoincrement ID
            $table->unsignedBigInteger('sold_property_id'); // Link to properties_sold
            $table->string('sold_price')->nullable();
            $table->string('sold_date')->nullable();
            $table->timestamps();
            
            // Foreign key
            $table->foreign('sold_property_id')->references('id')->on('properties_sold')->onDelete('cascade');
        });
        
        // Re-enable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('properties_sold_prices');
        Schema::dropIfExists('properties_sold');
    }
};
