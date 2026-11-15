<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_tags', function (Blueprint $table): void {
            $table->boolean('is_public')->default(false)->index();
        });

        // Backfill existing tags (manual/user tags) to visible.
        DB::table('media_tags')->update(['is_public' => true]);
    }

    public function down(): void
    {
        Schema::table('media_tags', function (Blueprint $table): void {
            $table->dropIndex(['is_public']);
            $table->dropColumn('is_public');
        });
    }
};
