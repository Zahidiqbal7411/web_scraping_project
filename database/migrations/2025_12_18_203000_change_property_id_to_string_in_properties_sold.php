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
            // Change property_id to string to support UUIDs/alphanumeric IDs from Rightmove
            $table->string('property_id', 255)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('properties_sold', function (Blueprint $table) {
            // Attempt to revert (might fail if data contains non-integers, but that's expected on down)
            $table->integer('property_id')->change();
        });
    }
};
