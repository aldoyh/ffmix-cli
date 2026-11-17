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
        Schema::create('normalized_assets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('show_preset_id')->nullable()->constrained('show_presets')->nullOnDelete();
            $table->string('source_path')->unique();
            $table->string('normalized_path');
            $table->string('asset_type')->default('video');
            $table->unsignedBigInteger('source_mtime')->default(0);
            $table->unsignedBigInteger('source_size')->default(0);
            $table->string('status')->default('pending')->index();
            $table->float('duration_seconds')->default(0.0);
            $table->text('error_message')->nullable();
            $table->timestamp('normalized_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('normalized_assets');
    }
};
