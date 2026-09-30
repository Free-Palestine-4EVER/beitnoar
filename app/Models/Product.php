<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'name_en',
        'name_ar',
        'description_en',
        'description_ar',
        'price',
        'calories',
        'image',
        'video',
        'video_poster',
        'model_glb',
        'model_usdz',
        'ar_enabled',
        'is_active',
        'is_featured',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'ar_enabled' => 'boolean',
        'price' => 'decimal:2',
        'calories' => 'integer',
        'sort_order' => 'integer',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    public function getImageUrlAttribute(): ?string
    {
        if ($this->image) {
            return $this->mediaUrl($this->image, $this->optimizedImagePath($this->image));
        }

        return null;
    }

    public function getVideoUrlAttribute(): ?string
    {
        if ($this->video) {
            return $this->mediaUrl($this->video, $this->optimizedVideoPath($this->video));
        }

        return null;
    }

    public function getVideoPosterUrlAttribute(): ?string
    {
        if ($this->video_poster) {
            return Storage::disk('public')->url($this->video_poster);
        }

        return null;
    }

    public function getModelGlbUrlAttribute(): ?string
    {
        if ($this->model_glb) {
            return $this->versionedModelUrl($this->model_glb);
        }

        return null;
    }

    public function getModelUsdzUrlAttribute(): ?string
    {
        if ($this->model_usdz) {
            return $this->versionedModelUrl($this->model_usdz);
        }

        return null;
    }

    public function getHasArAttribute(): bool
    {
        return (bool) ($this->ar_enabled && $this->model_glb);
    }

    private function mediaUrl(string $originalPath, ?string $optimizedPath): string
    {
        $disk = Storage::disk('public');

        if ($optimizedPath && $disk->exists($optimizedPath)) {
            return $disk->url($optimizedPath);
        }

        return $disk->url($originalPath);
    }

    private function optimizedImagePath(string $path): ?string
    {
        $relativePath = $this->publicProductPath($path);

        if (!$relativePath) {
            return null;
        }

        $directory = dirname($relativePath);
        $directory = $directory === '.' ? '' : $directory.'/';

        return 'products/optimized/images/'.$directory.pathinfo($relativePath, PATHINFO_FILENAME).'.webp';
    }

    private function optimizedVideoPath(string $path): ?string
    {
        $relativePath = $this->publicProductPath($path);

        if (!$relativePath || !str_starts_with($relativePath, 'videos/')) {
            return null;
        }

        return 'products/optimized/videos/'.basename($relativePath);
    }

    private function publicProductPath(string $path): ?string
    {
        $path = parse_url($path, PHP_URL_PATH) ?: $path;
        $path = ltrim(str_replace('\\', '/', $path), '/');

        if (str_starts_with($path, 'storage/')) {
            $path = substr($path, strlen('storage/'));
        }

        if (!str_starts_with($path, 'products/')) {
            return null;
        }

        $relativePath = substr($path, strlen('products/'));

        if (str_contains($relativePath, '../') || $relativePath === '') {
            return null;
        }

        return $relativePath;
    }

    private function versionedModelUrl(string $path): string
    {
        $url = Storage::disk('public')->url($path);

        return $url.(str_contains($url, '?') ? '&' : '?').'v=real-size-1';
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    public function scopeSorted(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }
}
