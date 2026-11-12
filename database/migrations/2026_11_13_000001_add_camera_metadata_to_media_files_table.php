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
        Schema::table('media_files', function (Blueprint $table): void {
            $table->string('camera_make', 100)->nullable()->after('place_name');
            $table->string('camera_model', 150)->nullable()->after('camera_make');
            $table->string('lens_model', 150)->nullable()->after('camera_model');
            $table->string('f_number', 20)->nullable()->after('lens_model');
            $table->string('exposure_time', 30)->nullable()->after('f_number');
            $table->unsignedInteger('iso')->nullable()->after('exposure_time');
            $table->string('focal_length', 30)->nullable()->after('iso');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('media_files', function (Blueprint $table): void {
            $table->dropColumn([
                'camera_make',
                'camera_model',
                'lens_model',
                'f_number',
                'exposure_time',
                'iso',
                'focal_length',
            ]);
        });
    }
};
