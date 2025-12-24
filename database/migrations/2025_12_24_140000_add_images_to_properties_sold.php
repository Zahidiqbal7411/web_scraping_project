<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Add missing columns to properties_sold table for images and address details
     */
    public function up(): void
    {
        Schema::table('properties_sold', function (Blueprint $table) {
            if (!Schema::hasColumn('properties_sold', 'image_url')) {
                $table->text('image_url')->nullable()->after('tenure');
            }
            if (!Schema::hasColumn('properties_sold', 'images')) {
                $table->json('images')->nullable()->after('image_url');
            }
            if (!Schema::hasColumn('properties_sold', 'house_number')) {
                $table->string('house_number')->nullable()->after('location');
            }
            if (!Schema::hasColumn('properties_sold', 'road_name')) {
                $table->string('road_name')->nullable()->after('house_number');
            }
            if (!Schema::hasColumn('properties_sold', 'map_url')) {
                $table->text('map_url')->nullable()->after('images');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('properties_sold', function (Blueprint $table) {
            $table->dropColumn(['image_url', 'images', 'house_number', 'road_name', 'map_url']);
        });
    }
};
