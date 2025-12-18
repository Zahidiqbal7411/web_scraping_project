<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Add detail_url column to properties_sold for individual property sold detail page links
     */
    public function up(): void
    {
        Schema::table('properties_sold', function (Blueprint $table) {
            // Add column to store the individual sold property detail URL
            $table->text('detail_url')->nullable()->after('source_sold_link');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('properties_sold', function (Blueprint $table) {
            $table->dropColumn('detail_url');
        });
    }
};
