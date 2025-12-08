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
        Schema::create('saved_searches', function (Blueprint $table) {
            $table->id();
            $table->string('area')->nullable();
            $table->decimal('min_price', 15, 2)->nullable();
            $table->decimal('max_price', 15, 2)->nullable();
            $table->integer('min_bed')->nullable();
            $table->integer('max_bed')->nullable();
            $table->integer('min_bath')->nullable();
            $table->integer('max_bath')->nullable();
            $table->string('property_type')->nullable();
            $table->string('tenure_types')->nullable();
            $table->string('must_have')->nullable();
            $table->string('dont_show')->nullable();
            $table->text('updates_url');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('saved_searches');
    }
};
