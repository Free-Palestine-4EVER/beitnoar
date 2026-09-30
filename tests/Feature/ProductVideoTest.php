<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Services\MenuCacheService;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductVideoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        MenuCacheService::clear();
    }

    public function test_product_video_urls_are_null_when_no_video_uploaded(): void
    {
        $product = new Product;
        $this->assertNull($product->video_url);
        $this->assertNull($product->video_poster_url);
    }

    public function test_product_video_urls_return_storage_url_when_present(): void
    {
        Storage::fake('public');

        $product = new Product([
            'video' => 'products/videos/test.mp4',
            'video_poster' => 'products/posters/test.jpg',
        ]);

        $this->assertNotNull($product->video_url);
        $this->assertStringContainsString('products/videos/test.mp4', $product->video_url);

        $this->assertNotNull($product->video_poster_url);
        $this->assertStringContainsString('products/posters/test.jpg', $product->video_poster_url);
    }

    public function test_product_image_and_video_urls_prefer_generated_optimized_assets(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('products/optimized/images/dish.webp', 'optimized image');
        Storage::disk('public')->put('products/optimized/videos/dish.mp4', 'optimized video');

        $product = new Product([
            'image' => 'products/dish.jpg',
            'video' => 'products/videos/dish.mp4',
        ]);

        $this->assertStringContainsString('products/dish.jpg', $product->image_url);
        $this->assertStringContainsString('products/videos/dish.mp4', $product->video_url);

        Storage::disk('public')->put('products/optimized/images/dish.webp', 'optimized image');
        Storage::disk('public')->put('products/optimized/videos/dish.mp4', 'optimized video');

        $product = new Product([
            'image' => 'products/dish.jpg',
            'video' => 'products/videos/dish.mp4',
        ]);

        $this->assertStringContainsString('products/optimized/images/dish.webp', $product->image_url);
        $this->assertStringContainsString('products/optimized/videos/dish.mp4', $product->video_url);
    }

    public function test_menu_api_includes_video_and_video_poster_urls(): void
    {
        Storage::fake('public');

        $cat = Category::first();
        $product = Product::create([
            'category_id' => $cat->id,
            'name_en' => 'Video Dish Test',
            'name_ar' => 'فيديو تجربة',
            'price' => 12.5,
            'is_active' => true,
            'video' => 'products/videos/sample.mp4',
            'video_poster' => 'products/posters/sample.jpg',
        ]);

        $response = $this->getJson('/api/menu');
        $response->assertStatus(200);

        $data = $response->json('data');

        // Find product in menu tree
        $found = null;
        foreach ($data as $c) {
            foreach ($c['products'] ?? [] as $p) {
                if ($p['id'] === $product->id) {
                    $found = $p;
                    break 2;
                }
            }
            foreach ($c['children'] ?? [] as $sub) {
                foreach ($sub['products'] ?? [] as $p) {
                    if ($p['id'] === $product->id) {
                        $found = $p;
                        break 3;
                    }
                }
            }
        }

        $this->assertNotNull($found, 'Created product should be present in API response');
        $this->assertStringContainsString('products/videos/sample.mp4', $found['video_url']);
        $this->assertStringContainsString('products/posters/sample.jpg', $found['video_poster_url']);

        $product->delete();
    }
}
