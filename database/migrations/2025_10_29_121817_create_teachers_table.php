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
        Schema::create('teachers', function (Blueprint $table) {
            $table->id();
            $table->string('teacher_name', 255)->nullable(); // optional
            $table->string('teacher_contact', 255)->nullable(); // optional
            $table->string('teacher_email', 255)->nullable(); // optional
            $table->string('teacher_other_info', 255)->nullable(); 
            $table->string('course', 255)->nullable();
             $table->string('selected_course', 255)->nullable();
            $table->decimal('teacher_percentage' ,7,3)->nulllable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teachers');
    }
};
