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
        // Drop existing tables if they exist to avoid conflicts
        Schema::dropIfExists('property_images');
        Schema::dropIfExists('properties');

        Schema::create('properties', function (Blueprint $table) {
            $table->unsignedBigInteger('property_id')->primary(); // Using property_id as Primary Key
            $table->string('location')->nullable();
            $table->string('price')->nullable();
            $table->text('key_features')->nullable();
            $table->longText('description')->nullable();
            $table->text('sold_link')->nullable();
            
            // Foreign key to saved_searches
            $table->unsignedBigInteger('filter_id')->nullable();
            $table->foreign('filter_id')->references('id')->on('saved_searches')->onDelete('cascade');

            $table->timestamps();
        });

        Schema::create('property_images', function (Blueprint $table) {
            $table->integer('image_id')->autoIncrement(); // Using image_id as Primary Key (int 10)
            $table->text('image_link')->nullable();
            
            // Foreign key to properties
            $table->unsignedBigInteger('property_id');
            $table->foreign('property_id')->references('property_id')->on('properties')->onDelete('cascade');
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('property_images');
        Schema::dropIfExists('properties');
    }
};
