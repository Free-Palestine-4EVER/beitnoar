<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MenuProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name_en' => $this->name_en,
            'name_ar' => $this->name_ar,
            'description_en' => $this->description_en,
            'description_ar' => $this->description_ar,
            'price' => (float) $this->price,
            'calories' => $this->calories,
            'image_url' => $this->image_url,
            'video_url' => $this->video_url,
            'video_poster_url' => $this->video_poster_url,
            'model_glb_url' => $this->model_glb_url,
            'model_usdz_url' => $this->model_usdz_url,
            'ar_enabled' => (bool) $this->ar_enabled,
            'has_ar' => (bool) $this->has_ar,
            'is_featured' => $this->is_featured,
            'tags' => $this->relationLoaded('tags')
                ? MenuTagResource::collection($this->tags)->resolve()
                : [],
        ];
    }
}
