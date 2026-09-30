<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MenuTagResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'name_en' => $this->name_en,
            'name_ar' => $this->name_ar,
            'icon' => $this->icon,
        ];
    }
}
