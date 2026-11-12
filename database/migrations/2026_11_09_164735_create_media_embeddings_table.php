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
        Schema::create('media_embeddings', function (Blueprint $table): void {
            $table->foreignId('media_file_id')
                ->primary()
                ->constrained('media_files')
                ->cascadeOnDelete();
            $table->timestampsTz();
        });
        DB::statement('CREATE EXTENSION IF NOT EXISTS vector;');
        DB::statement('ALTER TABLE media_embeddings ADD COLUMN embedding vector(512)');
        DB::statement('CREATE INDEX media_embeddings_hnsw_idx ON media_embeddings USING hnsw (embedding vector_cosine_ops)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('media_embeddings');
    }
};
