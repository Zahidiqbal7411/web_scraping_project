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
        Schema::table('schedules', function (Blueprint $table) {
            $table->unsignedBigInteger('import_session_id')->nullable()->after('saved_search_id');
            $table->boolean('url_import_completed')->default(false)->after('total_pages');
            $table->boolean('property_import_completed')->default(false)->after('url_import_completed');
            $table->boolean('sold_import_completed')->default(false)->after('property_import_completed');

            $table->foreign('import_session_id')
                  ->references('id')
                  ->on('import_sessions')
                  ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropForeign(['import_session_id']);
            $table->dropColumn(['import_session_id', 'url_import_completed', 'property_import_completed', 'sold_import_completed']);
        });
    }
};
