<?php

namespace App\Services;

use App\Models\Category;
use App\Http\Resources\MenuCategoryResource;

class MenuService
{
    public function getFullMenu(): array
    {
        $categories = Category::query()
            ->active()
            ->rootCategories()
            ->sorted()
            ->with([
                'children' => fn ($q) => $q->active()->sorted()->with([
                    'children' => fn ($q2) => $q2->active()->sorted()->with([
                        'products' => fn ($q3) => $q3->active()->sorted()->with([
                            'tags' => fn ($q4) => $q4->active()->sorted(),
                        ]),
                    ]),
                    'products' => fn ($q2) => $q2->active()->sorted()->with([
                        'tags' => fn ($q3) => $q3->active()->sorted(),
                    ]),
                ]),
                'products' => fn ($q) => $q->active()->sorted()->with([
                    'tags' => fn ($q2) => $q2->active()->sorted(),
                ]),
            ])
            ->get();

        return MenuCategoryResource::collection($categories)->resolve();
    }
}
