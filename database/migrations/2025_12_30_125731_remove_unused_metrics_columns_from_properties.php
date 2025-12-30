<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Remove unused metric columns that are calculated dynamically
     */
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            // Remove columns if they exist
            if (Schema::hasColumn('properties', 'average_sold_price')) {
                $table->dropColumn('average_sold_price');
            }
            if (Schema::hasColumn('properties', 'discount_metric')) {
                $table->dropColumn('discount_metric');
            }
            if (Schema::hasColumn('properties', 'sales_count')) {
                $table->dropColumn('sales_count');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->decimal('average_sold_price', 12, 2)->nullable();
            $table->decimal('discount_metric', 8, 2)->nullable();
            $table->integer('sales_count')->nullable();
        });
    }
};
