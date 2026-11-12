<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MediaType;
use Carbon\CarbonImmutable;
use Database\Factories\MediaFileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\RouteKey;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property ?int $media_source_id
 * @property string $file_path
 * @property string $storage_disk
 * @property string $file_hash
 * @property int $file_size
 * @property string $mime_type
 * @property MediaType $media_type
 * @property ?int $width
 * @property ?int $height
 * @property ?float $duration
 * @property ?CarbonImmutable $captured_at
 * @property CarbonImmutable $mtime
 * @property ?float $latitude
 * @property ?float $longitude
 * @property ?string $country
 * @property ?string $city
 * @property ?string $place_name
 * @property ?string $camera_make
 * @property ?string $camera_model
 * @property ?string $lens_model
 * @property ?string $f_number
 * @property ?string $exposure_time
 * @property ?int $iso
 * @property ?string $focal_length
 * @property ?string $dominant_color
 * @property bool $is_favorite
 * @property bool $is_ignored
 * @property ?string $preview_path
 * @property ?string $thumbnail_path
 * @property ?CarbonImmutable $deleted_at
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 * @property-read float|null $distance
 * @property-read ?MediaSource                                            $mediaSource
 * @property-read ?MediaEmbedding                                         $embedding
 * @property-read Collection<int, MediaTag> $tags
 *
 * @method static Builder<static>|MediaFile active()
 * @method static Builder<static>|MediaFile ignored()$tags_count
 */
#[Fillable([
    'uuid',
    'media_source_id',
    'file_path',
    'storage_disk',
    'file_hash',
    'file_size',
    'mime_type',
    'media_type',
    'width',
    'height',
    'duration',
    'captured_at',
    'mtime',
    'latitude',
    'longitude',
    'country',
    'city',
    'place_name',
    'camera_make',
    'camera_model',
    'lens_model',
    'f_number',
    'exposure_time',
    'iso',
    'focal_length',
    'dominant_color',
    'is_favorite',
    'is_ignored',
    'preview_path',
    'thumbnail_path',
])]
#[Hidden(['id'])]
#[RouteKey('uuid')]
class MediaFile extends Model
{
    /** @use HasFactory<MediaFileFactory> */
    use HasFactory;

    use SoftDeletes;

    protected static function booted(): void
    {
        static::creating(function (MediaFile $file): void {
            $file->uuid ??= (string) Str::uuid7();
        });
    }

    /**
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_ignored', false);
    }

    /**
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function ignored(Builder $query): void
    {
        $query->where('is_ignored', true);
    }

    protected function casts(): array
    {
        return [
            'media_source_id' => 'integer',
            'media_type' => MediaType::class,
            'file_size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'duration' => 'float',
            'captured_at' => 'immutable_datetime',
            'mtime' => 'immutable_datetime',
            'latitude' => 'float',
            'longitude' => 'float',
            'is_favorite' => 'boolean',
            'is_ignored' => 'boolean',
            'deleted_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<MediaSource, $this>
     */
    public function mediaSource(): BelongsTo
    {
        return $this->belongsTo(MediaSource::class);
    }

    /**
     * @return HasOne<MediaEmbedding, $this>
     */
    public function embedding(): HasOne
    {
        return $this->hasOne(MediaEmbedding::class);
    }

    /**
     * @return BelongsToMany<MediaTag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(MediaTag::class, 'media_file_tag', 'media_file_id', 'tag_id')->withTimestamps();
    }
}
