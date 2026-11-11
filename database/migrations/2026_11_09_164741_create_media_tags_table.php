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
        Schema::create('media_tags', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('slug', 100)->unique();
            $table->string('type', 50)->default('user')->index();
            $table->timestampsTz();
        });

        Schema::create('media_file_tag', function (Blueprint $table): void {
            $table->foreignId('media_file_id')
                ->constrained('media_files')
                ->cascadeOnDelete();
            $table->foreignId('tag_id')
                ->constrained('media_tags')
                ->cascadeOnDelete();
            $table->primary(['media_file_id', 'tag_id']);
            $table->timestampsTz();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('media_file_tag');
        Schema::dropIfExists('media_tags');
    }
};
