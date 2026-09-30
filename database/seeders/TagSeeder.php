<?php

namespace Database\Seeders;

use App\Models\Tag;
use Illuminate\Database\Seeder;

class TagSeeder extends Seeder
{
    public function run(): void
    {
        $tags = [
            ['name_en' => 'Vegan', 'name_ar' => 'نباتي', 'slug' => 'vegan', 'sort_order' => 1],
            ['name_en' => 'Vegetarian', 'name_ar' => 'نباتي بالحليب', 'slug' => 'vegetarian', 'sort_order' => 2],
            ['name_en' => 'Gluten Free', 'name_ar' => 'خالي من الغلوتين', 'slug' => 'gluten-free', 'sort_order' => 3],
            ['name_en' => 'Signature', 'name_ar' => 'طبق البيت', 'slug' => 'signature', 'sort_order' => 4],
            ['name_en' => 'To Share', 'name_ar' => 'للمشاركة', 'slug' => 'to-share', 'sort_order' => 5],
            ['name_en' => 'Caffeine Free', 'name_ar' => 'بدون كافيين', 'slug' => 'caffeine-free', 'sort_order' => 6],
            ['name_en' => 'Raw', 'name_ar' => 'نية', 'slug' => 'raw', 'sort_order' => 7],
        ];

        foreach ($tags as $tag) {
            Tag::updateOrCreate(['slug' => $tag['slug']], $tag);
        }
    }
}
