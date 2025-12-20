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
        Schema::table('properties', function (Blueprint $table) {
            $table->string('house_number')->nullable()->after('location');
            $table->string('road_name')->nullable()->after('house_number');
        });

        Schema::table('properties_sold', function (Blueprint $table) {
            $table->string('house_number')->nullable()->after('location');
            $table->string('road_name')->nullable()->after('house_number');
            $table->text('image_url')->nullable()->after('road_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn(['house_number', 'road_name']);
        });

        Schema::table('properties_sold', function (Blueprint $table) {
            $table->dropColumn(['house_number', 'road_name', 'image_url']);
        });
    }
};
