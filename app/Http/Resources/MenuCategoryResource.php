<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class MenuCategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name_en' => $this->name_en,
            'name_ar' => $this->name_ar,
            'description_en' => $this->description_en,
            'description_ar' => $this->description_ar,
            'icon' => $this->icon,
            'image' => $this->image ? Storage::disk('public')->url($this->image) : null,
            'children' => $this->relationLoaded('children')
                ? MenuCategoryResource::collection($this->children)->resolve()
                : [],
            'products' => $this->relationLoaded('products')
                ? MenuProductResource::collection($this->products)->resolve()
                : [],
        ];
    }
}
