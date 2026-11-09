<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\MediaSourceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $path
 * @property bool $is_active
 * @property int $scan_interval_hours
 * @property ?CarbonImmutable $last_scanned_at
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 * @property-read Collection<int, MediaFile> $mediaFiles
 * @property-read int|null $media_files_count
 */
#[Fillable(['name', 'path', 'is_active', 'scan_interval_hours', 'last_scanned_at'])]
class MediaSource extends Model
{
    /** @use HasFactory<MediaSourceFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'scan_interval_hours' => 'integer',
            'last_scanned_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return HasMany<MediaFile, $this>
     */
    public function mediaFiles(): HasMany
    {
        return $this->hasMany(MediaFile::class);
    }
}
