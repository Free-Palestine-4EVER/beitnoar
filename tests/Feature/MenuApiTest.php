<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Services\MenuCacheService;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class MenuApiTest extends TestCase
{
    public function test_menu_endpoint_returns_success_and_active_items_only(): void
    {
        $response = $this->getJson('/api/menu');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name_en',
                        'name_ar',
                        'children',
                        'products',
                    ],
                ],
            ]);
    }

    public function test_inactive_category_is_not_returned(): void
    {
        MenuCacheService::clear();

        $inactiveCat = Category::create([
            'name_en' => 'Hidden Secret Category',
            'name_ar' => 'قسم سري',
            'is_active' => false,
        ]);

        $response = $this->getJson('/api/menu');
        $response->assertStatus(200);

        $json = $response->json('data');
        $ids = collect($json)->pluck('id')->all();

        $this->assertNotContains($inactiveCat->id, $ids);
        $inactiveCat->delete();
    }

    public function test_cache_is_cleared_when_product_is_created(): void
    {
        MenuCacheService::get(); // prime cache
        $this->assertTrue(Cache::store('file')->has(MenuCacheService::CACHE_KEY));

        $cat = Category::first();
        $testProduct = Product::create([
            'category_id' => $cat->id,
            'name_en' => 'Test Item Cache',
            'name_ar' => 'عنصر فحص',
            'price' => 5.0,
            'is_active' => true,
        ]);

        $this->assertFalse(Cache::store('file')->has(MenuCacheService::CACHE_KEY));
        $testProduct->delete();
    }
}
