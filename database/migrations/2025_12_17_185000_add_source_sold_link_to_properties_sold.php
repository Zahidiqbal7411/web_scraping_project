<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Add source_sold_link column to properties_sold for URL-based matching
     */
    public function up(): void
    {
        Schema::table('properties_sold', function (Blueprint $table) {
            // Add column to store the sold_link URL this record came from
            $table->text('source_sold_link')->nullable()->after('tenure');
            $table->index('source_sold_link', 'idx_source_sold_link');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('properties_sold', function (Blueprint $table) {
            $table->dropIndex('idx_source_sold_link');
            $table->dropColumn('source_sold_link');
        });
    }
};
