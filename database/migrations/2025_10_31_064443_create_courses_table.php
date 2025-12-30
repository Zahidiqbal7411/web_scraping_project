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
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->string('course_title', 255);

            $table->unsignedBigInteger('edu_system_id');
            $table->unsignedBigInteger('exam_board_id');
            $table->unsignedBigInteger('subject_id');

            $table->foreign('edu_system_id')
                ->references('id')
                ->on('taxonomies_educational_systems')
                ->onDelete('cascade');

            $table->foreign('exam_board_id')
                ->references('id')
                ->on('taxonomies_examination_boards')
                ->onDelete('cascade');

            $table->foreign('subject_id')
                ->references('id')
                ->on('taxonomies_subjects')
                ->onDelete('cascade');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};
