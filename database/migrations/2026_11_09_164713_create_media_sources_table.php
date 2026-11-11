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
        Schema::create('media_sources', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 255);
            $table->string('path', 1024);
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('scan_interval_hours')->default(24);
            $table->timestampTz('last_scanned_at')->nullable();
            $table->timestampsTz();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('media_sources');
    }
};
