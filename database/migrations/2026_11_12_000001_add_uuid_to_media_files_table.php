<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('media_files', function (Blueprint $table): void {
            $table->uuid('uuid')->nullable()->unique()->after('id');
        });

        $files = DB::table('media_files')->whereNull('uuid')->select('id')->get();
        foreach ($files as $file) {
            DB::table('media_files')->where('id', $file->id)->update([
                'uuid' => (string) Str::uuid7(),
            ]);
        }

        Schema::table('media_files', function (Blueprint $table): void {
            $table->uuid('uuid')->change()->nullable(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('media_files', function (Blueprint $table): void {
            $table->dropColumn('uuid');
        });
    }
};
