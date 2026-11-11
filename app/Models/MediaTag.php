<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TagType;
use Database\Factories\MediaTagFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property TagType $type
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 * @property-read Collection<int, MediaFile> $mediaFiles
 * @property-read int|null $media_files_count
 */
#[Fillable(['name', 'slug', 'type'])]
class MediaTag extends Model
{
    /** @use HasFactory<MediaTagFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'type' => TagType::class,
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $tag): void {
            if (empty($tag->slug)) {
                $tag->slug = Str::slug($tag->name);
            }
        });
    }

    /**
     * @return BelongsToMany<MediaFile, $this>
     */
    public function mediaFiles(): BelongsToMany
    {
        return $this->belongsToMany(MediaFile::class, 'media_file_tag', 'tag_id', 'media_file_id')->withTimestamps();
    }
}
