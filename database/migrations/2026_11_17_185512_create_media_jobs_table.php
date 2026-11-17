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
        Schema::create('media_jobs', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('input_path');
            $table->string('output_path');
            $table->string('preset_slug')->nullable();
            $table->json('pipeline_config');
            $table->text('command_preview')->nullable();
            $table->string('status')->default('pending')->index();
            $table->unsignedTinyInteger('worker_slot')->nullable();
            $table->float('progress_percent')->default(0);
            $table->float('fps')->default(0);
            $table->string('speed')->nullable();
            $table->string('bitrate')->nullable();
            $table->float('duration_seconds')->default(0);
            $table->float('processed_seconds')->default(0);
            $table->integer('exit_code')->nullable();
            $table->text('error_message')->nullable();
            $table->text('log_tail')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('media_jobs');
    }
};
