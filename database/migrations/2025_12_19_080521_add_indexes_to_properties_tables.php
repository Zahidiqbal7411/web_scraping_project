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
        // Add indexes to properties table for faster filtering and lookups
        Schema::table('properties', function (Blueprint $table) {
            $table->index('filter_id', 'idx_properties_filter_id');
            $table->index('property_id', 'idx_properties_property_id');
            $table->index(['filter_id', 'created_at'], 'idx_properties_filter_created');
        });
        
        // Add indexes to properties_sold table for faster joins
        Schema::table('properties_sold', function (Blueprint $table) {
            $table->index('property_id', 'idx_sold_property_id');
            $table->index('source_sold_link', 'idx_sold_source_link');
        });
        
        // Add indexes to property_images table for faster image loading
        Schema::table('property_images', function (Blueprint $table) {
            $table->index('property_id', 'idx_images_property_id');
        });
        
        // Add indexes to properties_sold_prices table for faster price lookups
        Schema::table('properties_sold_prices', function (Blueprint $table) {
            $table->index('sold_property_id', 'idx_prices_sold_property_id');
        });
        
        // Add indexes to urls table for faster filtering
        Schema::table('urls', function (Blueprint $table) {
            $table->index('filter_id', 'idx_urls_filter_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropIndex('idx_properties_filter_id');
            $table->dropIndex('idx_properties_property_id');
            $table->dropIndex('idx_properties_filter_created');
        });
        
        Schema::table('properties_sold', function (Blueprint $table) {
            $table->dropIndex('idx_sold_property_id');
            $table->dropIndex('idx_sold_source_link');
        });
        
        Schema::table('property_images', function (Blueprint $table) {
            $table->dropIndex('idx_images_property_id');
        });
        
        Schema::table('properties_sold_prices', function (Blueprint $table) {
            $table->dropIndex('idx_prices_sold_property_id');
        });
        
        Schema::table('urls', function (Blueprint $table) {
            $table->dropIndex('idx_urls_filter_id');
        });
    }
};
