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
        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('saved_search_id')->nullable();
            $table->unsignedBigInteger('import_session_id')->nullable(); // Foreign Key
            $table->string('name');                          // Display name
            $table->text('url');                             // Rightmove search URL
            $table->tinyInteger('status')->default(0);       // 0=pending, 1=importing, 2=completed, 3=failed
            $table->integer('total_properties')->default(0); // Total properties found
            $table->integer('imported_count')->default(0);   // Properties imported so far
            $table->integer('current_page')->default(0);     // Current page being processed
            $table->integer('total_pages')->default(0);      // Total pages to process
            $table->boolean('url_import_completed')->default(false);
            $table->boolean('property_import_completed')->default(false);
            $table->boolean('sold_import_completed')->default(false);
            $table->text('error_message')->nullable();       // Error message if failed
            $table->timestamp('started_at')->nullable();     // When import started
            $table->timestamp('completed_at')->nullable();   // When import completed
            $table->timestamps();

            $table->foreign('saved_search_id')
                  ->references('id')
                  ->on('saved_searches')
                  ->onDelete('set null');

            $table->foreign('import_session_id')
                  ->references('id')
                  ->on('import_sessions')
                  ->onDelete('set null');
            
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedules');
    }
};
