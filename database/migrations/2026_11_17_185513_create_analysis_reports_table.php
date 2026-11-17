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
        Schema::create('analysis_reports', function (Blueprint $table): void {
            $table->id();
            $table->string('kind')->index();
            $table->string('source_path');
            $table->string('reference_path')->nullable();
            $table->json('summary');
            $table->json('series')->nullable();
            $table->text('raw_output')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('analysis_reports');
    }
};
