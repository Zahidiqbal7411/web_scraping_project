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
        // 1. Add new columns to 'properties' table
        Schema::table('properties', function (Blueprint $table) {
            $table->string('bedrooms')->nullable()->after('price');
            $table->string('bathrooms')->nullable()->after('bedrooms');
            $table->string('property_type')->nullable()->after('bathrooms');
            $table->string('size')->nullable()->after('property_type');
            $table->string('tenure')->nullable()->after('size');
            $table->string('council_tax')->nullable()->after('tenure');
            $table->string('parking')->nullable()->after('council_tax');
            $table->string('garden')->nullable()->after('parking');
            $table->string('accessibility')->nullable()->after('garden');
            
            // Leasehold details
            $table->string('ground_rent')->nullable()->after('accessibility');
            $table->string('annual_service_charge')->nullable()->after('ground_rent');
            $table->string('lease_length')->nullable()->after('annual_service_charge');
        });

        // 2. Create 'properties_sold' table
        Schema::create('properties_sold', function (Blueprint $table) {
            $table->id(); // BigInt PK
            $table->unsignedBigInteger('property_id')->nullable(); // Rightmove ID or link to properties? User asked for property_id unsigned 20.
            
            $table->string('location')->nullable();
            $table->string('property_type')->nullable();
            $table->string('bedrooms')->nullable();
            $table->string('bathrooms')->nullable(); // User asked for 'bath'
            $table->string('tenure')->nullable();
            
            // Should this link to 'properties' table (current property being viewed)?
            // "when user click on import then the sold_property data also needed to be go to database with urls, properties"
            // It implies a relationship. Let's add an FK to saved_searches or properties?
            // The user didn't explicitly ask for an FK to properties, but "property_id" usually implies it. 
            // However, since sold properties are distinct entities, this 'property_id' likely refers to the Rightmove ID of the SOLD property itself.
            // But we can also add a 'origin_property_id' if we want to track which property led to this. 
            // For now, I'll stick to the requested fields.
            
            $table->timestamps();
        });

        // 3. Create 'properties_sold_prices' table
        Schema::create('properties_sold_prices', function (Blueprint $table) {
            $table->id();
            
            // Foreign key to properties_sold
            $table->unsignedBigInteger('sold_property_id');
            $table->foreign('sold_property_id')->references('id')->on('properties_sold')->onDelete('cascade');
            
            $table->string('sold_price')->nullable();
            $table->string('sold_date')->nullable();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('properties_sold_prices');
        Schema::dropIfExists('properties_sold');
        
        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn([
                'bedrooms', 'bathrooms', 'property_type', 'size', 'tenure', 
                'council_tax', 'parking', 'garden', 'accessibility',
                'ground_rent', 'annual_service_charge', 'lease_length'
            ]);
        });
    }
};
