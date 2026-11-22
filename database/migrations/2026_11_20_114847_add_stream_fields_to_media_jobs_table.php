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
        Schema::table('media_jobs', function (Blueprint $table): void {
            $table->string('source_url')->nullable()->index();
            $table->string('session_key')->nullable()->index();
            $table->string('stream_type')->nullable()->index();
            $table->unsignedInteger('record_seconds')->nullable();
            $table->string('dsk_template')->nullable();
            $table->string('dsk_title')->nullable();
            $table->string('dsk_subtitle')->nullable();
            $table->string('dsk_accent')->nullable();
            $table->float('dsk_start')->nullable();
            $table->float('dsk_duration')->nullable();
            $table->boolean('has_overlay')->default(false);
            $table->boolean('live_output')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('media_jobs', function (Blueprint $table): void {
            $table->dropColumn([
                'source_url',
                'session_key',
                'stream_type',
                'record_seconds',
                'dsk_template',
                'dsk_title',
                'dsk_subtitle',
                'dsk_accent',
                'dsk_start',
                'dsk_duration',
                'has_overlay',
                'live_output',
            ]);
        });
    }
};
