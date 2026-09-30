<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Services\MenuCacheService;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductArTest extends TestCase
{
    public function test_product_ar_attributes_and_accessors(): void
    {
        Storage::fake('public');

        $category = Category::first() ?? Category::create([
            'name_en' => 'AR Test Category',
            'name_ar' => 'تصنيف الواقع المعزز',
            'is_active' => true,
        ]);

        $productWithAr = Product::create([
            'category_id' => $category->id,
            'name_en' => 'Plate with 3D Model',
            'name_ar' => 'طبق ثلاثي الأبعاد',
            'price' => 12.50,
            'is_active' => true,
            'model_glb' => 'products/models/dish.glb',
            'model_usdz' => 'products/models/dish.usdz',
            'ar_enabled' => true,
        ]);

        $this->assertTrue($productWithAr->ar_enabled);
        $this->assertTrue($productWithAr->has_ar);
        $this->assertStringContainsString('products/models/dish.glb', $productWithAr->model_glb_url);
        $this->assertStringContainsString('products/models/dish.usdz', $productWithAr->model_usdz_url);

        $productDisabledAr = Product::create([
            'category_id' => $category->id,
            'name_en' => 'Plate with Disabled AR',
            'name_ar' => 'طبق معطل الواقع المعزز',
            'price' => 8.00,
            'is_active' => true,
            'model_glb' => 'products/models/dish2.glb',
            'ar_enabled' => false,
        ]);

        $this->assertFalse($productDisabledAr->ar_enabled);
        $this->assertFalse($productDisabledAr->has_ar);

        $productNoModel = Product::create([
            'category_id' => $category->id,
            'name_en' => 'Normal Plate',
            'name_ar' => 'طبق عادي',
            'price' => 6.00,
            'is_active' => true,
            'ar_enabled' => true,
            'model_glb' => null,
        ]);

        $this->assertFalse($productNoModel->has_ar);
        $this->assertNull($productNoModel->model_glb_url);
        $this->assertNull($productNoModel->model_usdz_url);
    }

    public function test_menu_api_exposes_ar_fields(): void
    {
        MenuCacheService::clear();

        $category = Category::first();
        $product = Product::create([
            'category_id' => $category->id,
            'name_en' => 'API AR Dish',
            'name_ar' => 'طبق بواجهة برمجة التطبيقات',
            'price' => 15.00,
            'is_active' => true,
            'model_glb' => 'products/models/api_dish.glb',
            'model_usdz' => 'products/models/api_dish.usdz',
            'ar_enabled' => true,
        ]);

        $response = $this->getJson('/api/menu');
        $response->assertStatus(200);

        $categories = $response->json('data');
        $foundProduct = null;

        foreach ($categories as $cat) {
            foreach ($cat['products'] ?? [] as $p) {
                if ($p['id'] === $product->id) {
                    $foundProduct = $p;
                    break 2;
                }
            }
            foreach ($cat['children'] ?? [] as $sub) {
                foreach ($sub['products'] ?? [] as $p) {
                    if ($p['id'] === $product->id) {
                        $foundProduct = $p;
                        break 3;
                    }
                }
            }
        }

        $this->assertNotNull($foundProduct);
        $this->assertTrue($foundProduct['ar_enabled']);
        $this->assertTrue($foundProduct['has_ar']);
        $this->assertNotNull($foundProduct['model_glb_url']);
        $this->assertNotNull($foundProduct['model_usdz_url']);
        $this->assertStringContainsString('products/models/api_dish.glb', $foundProduct['model_glb_url']);
        $this->assertStringContainsString('products/models/api_dish.usdz', $foundProduct['model_usdz_url']);
    }
}
