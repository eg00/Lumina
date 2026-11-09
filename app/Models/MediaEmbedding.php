<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\MediaEmbeddingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutIncrementing;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Override;

/**
 * @property int $media_file_id
 * @property list<float>|string|null $embedding
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 * @property-read MediaFile $mediaFile
 */
#[Fillable(['embedding', 'media_file_id'])]
#[WithoutIncrementing]
class MediaEmbedding extends Model
{
    /** @use HasFactory<MediaEmbeddingFactory> */
    use HasFactory;

    #[Override]
    protected $primaryKey = 'media_file_id';

    protected function casts(): array
    {
        return [
            'media_file_id' => 'integer',
        ];
    }

    /**
     * @return Attribute<mixed, string>
     */
    protected function embedding(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): mixed => $value ? json_decode(str_replace(['[', ']'], ['[', ']'], $value), true) : null,
            set: fn (array|string $value): string => is_array($value) ? '['.implode(',', $value).']' : $value,
        );
    }

    /**
     * @return BelongsTo<MediaFile, $this>
     */
    public function mediaFile(): BelongsTo
    {
        return $this->belongsTo(MediaFile::class);
    }
}
