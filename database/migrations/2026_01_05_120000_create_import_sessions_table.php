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
        Schema::create('import_sessions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('saved_search_id')->nullable();
            $table->string('base_url', 2048);
            $table->enum('status', ['pending', 'processing', 'completed', 'failed', 'cancelled'])->default('pending');
            
            // Job tracking
            $table->unsignedInteger('total_jobs')->default(0);
            $table->unsignedInteger('completed_jobs')->default(0);
            $table->unsignedInteger('failed_jobs')->default(0);
            
            // Property tracking
            $table->unsignedInteger('total_properties')->default(0);
            $table->unsignedInteger('imported_properties')->default(0);
            $table->unsignedInteger('skipped_properties')->default(0);
            
            // Split tracking
            $table->unsignedInteger('split_count')->default(0);
            $table->unsignedInteger('max_depth_reached')->default(0);
            
            // Timing
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            
            // Error handling
            $table->text('error_message')->nullable();
            $table->json('split_details')->nullable();
            
            $table->timestamps();
            
            // Index for quick lookups
            $table->index(['saved_search_id', 'status']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('import_sessions');
    }
};
