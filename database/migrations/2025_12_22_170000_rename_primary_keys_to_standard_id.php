<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Disable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        // 1. Rename property_id to id in properties table
        if (Schema::hasColumn('properties', 'property_id')) {
            Schema::table('properties', function (Blueprint $table) {
                $table->renameColumn('property_id', 'id');
            });
        }

        // 2. Rename image_id to id in property_images table
        if (Schema::hasColumn('property_images', 'image_id')) {
            Schema::table('property_images', function (Blueprint $table) {
                $table->renameColumn('image_id', 'id');
            });
        }

        // Re-enable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        if (Schema::hasColumn('properties', 'id')) {
            Schema::table('properties', function (Blueprint $table) {
                $table->renameColumn('id', 'property_id');
            });
        }

        if (Schema::hasColumn('property_images', 'id')) {
            Schema::table('property_images', function (Blueprint $table) {
                $table->renameColumn('id', 'image_id');
            });
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
