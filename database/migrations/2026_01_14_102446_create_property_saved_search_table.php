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
        Schema::create('property_saved_search', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('property_id');
            $table->unsignedBigInteger('saved_search_id');
            $table->timestamps();

            // Foreign keys and indexes
            $table->foreign('property_id')->references('id')->on('properties')->onDelete('cascade');
            $table->foreign('saved_search_id')->references('id')->on('saved_searches')->onDelete('cascade');
            
            $table->unique(['property_id', 'saved_search_id']);
        });

        // Data Migration: Move existing filter_id to pivot table
        $properties = DB::table('properties')->whereNotNull('filter_id')->get(['id', 'filter_id']);
        
        $pivotData = [];
        foreach ($properties as $property) {
            $pivotData[] = [
                'property_id' => $property->id,
                'saved_search_id' => $property->filter_id,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            
            // Insert in chunks to avoid memory issues
            if (count($pivotData) >= 1000) {
                DB::table('property_saved_search')->insert($pivotData);
                $pivotData = [];
            }
        }
        
        if (!empty($pivotData)) {
            DB::table('property_saved_search')->insert($pivotData);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('property_saved_search');
    }
};
