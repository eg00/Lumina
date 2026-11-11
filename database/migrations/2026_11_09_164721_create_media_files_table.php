<?php

declare(strict_types=1);

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
        Schema::create('media_files', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('media_source_id')
                ->nullable()
                ->constrained('media_sources')
                ->nullOnDelete();
            $table->string('file_path', 1024);
            $table->string('storage_disk', 64)->default('local');
            $table->char('file_hash', 64)->index();
            $table->unsignedBigInteger('file_size');
            $table->string('mime_type', 128);
            $table->string('media_type', 32)->index();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->decimal('duration', 8, 2)->nullable();
            $table->timestampTz('captured_at')->nullable()->index();
            $table->timestampTz('mtime');
            $table->decimal('latitude', 10, 7)->nullable()->index();
            $table->decimal('longitude', 10, 7)->nullable()->index();
            $table->string('country', 100)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('place_name', 255)->nullable();
            $table->char('dominant_color', 7)->nullable();
            $table->boolean('is_favorite')->default(false)->index();
            $table->boolean('is_ignored')->default(false)->index();
            $table->string('preview_path', 1024)->nullable();
            $table->string('thumbnail_path', 1024)->nullable();
            $table->softDeletesTz();
            $table->timestampsTz();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('media_files');
    }
};
