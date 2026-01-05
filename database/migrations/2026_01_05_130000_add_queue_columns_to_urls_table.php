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
        Schema::table('urls', function (Blueprint $table) {
            if (!Schema::hasColumn('urls', 'rightmove_id')) {
                $table->string('rightmove_id')->nullable()->after('url');
            }
            if (!Schema::hasColumn('urls', 'saved_search_id')) {
                $table->unsignedBigInteger('saved_search_id')->nullable()->after('filter_id');
            }
            if (!Schema::hasColumn('urls', 'status')) {
                $table->string('status')->default('pending')->after('saved_search_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('urls', function (Blueprint $table) {
            $table->dropColumn(['rightmove_id', 'saved_search_id', 'status']);
        });
    }
};
