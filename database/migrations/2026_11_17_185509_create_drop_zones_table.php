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
        Schema::create('drop_zones', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('watch_path');
            $table->string('output_path');
            $table->string('preset_slug')->default('desktop');
            $table->json('extensions')->nullable();
            $table->string('post_action')->default('keep');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('processed_count')->default(0);
            $table->timestamp('last_scanned_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('drop_zones');
    }
};
