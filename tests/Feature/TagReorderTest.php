<?php

namespace Tests\Feature;

use App\Filament\Resources\Categories\Pages\ListCategories;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Resources\Tags\Pages\ListTags;
use App\Models\Category;
use App\Models\Product;
use App\Models\Tag;
use App\Models\User;
use App\Services\MenuCacheService;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\TestCase;

class TagReorderTest extends TestCase
{
    public function test_tags_page_loads_and_is_reorderable(): void
    {
        $user = User::first() ?? User::factory()->create();

        $this->actingAs($user)
            ->get('/admin/tags')
            ->assertSuccessful();

        $component = Livewire::actingAs($user)->test(ListTags::class);
        $component->assertSuccessful();

        $table = $component->instance()->getTable();
        $this->assertTrue($table->isReorderable());
        $this->assertEquals('sort_order', $table->getReorderColumn());
    }

    public function test_categories_page_loads_and_is_reorderable(): void
    {
        $user = User::first() ?? User::factory()->create();

        $this->actingAs($user)
            ->get('/admin/categories')
            ->assertSuccessful();

        $component = Livewire::actingAs($user)->test(ListCategories::class);
        $component->assertSuccessful();

        $table = $component->instance()->getTable();
        $this->assertTrue($table->isReorderable());
        $this->assertEquals('sort_order', $table->getReorderColumn());
    }

    public function test_products_page_loads_and_is_reorderable(): void
    {
        $user = User::first() ?? User::factory()->create();

        $this->actingAs($user)
            ->get('/admin/products')
            ->assertSuccessful();

        $component = Livewire::actingAs($user)->test(ListProducts::class);
        $component->assertSuccessful();

        $table = $component->instance()->getTable();
        $this->assertTrue($table->isReorderable());
        $this->assertEquals('sort_order', $table->getReorderColumn());
    }

    public function test_reordering_tags_persists_sort_order_and_invalidates_menu_cache(): void
    {
        $user = User::first() ?? User::factory()->create();

        // Ensure menu cache is primed
        MenuCacheService::get();
        $this->assertTrue(Cache::store('file')->has(MenuCacheService::CACHE_KEY));

        $tags = Tag::query()->orderBy('id')->take(3)->get();
        $this->assertGreaterThanOrEqual(3, $tags->count());

        $reorderedKeys = [
            $tags[2]->id,
            $tags[0]->id,
            $tags[1]->id,
        ];

        Livewire::actingAs($user)
            ->test(ListTags::class)
            ->call('reorderTable', $reorderedKeys)
            ->assertSuccessful();

        // Check that sort_order was updated
        $this->assertEquals(1, Tag::find($tags[2]->id)->sort_order);
        $this->assertEquals(2, Tag::find($tags[0]->id)->sort_order);
        $this->assertEquals(3, Tag::find($tags[1]->id)->sort_order);

        // Check that menu cache was cleared
        $this->assertFalse(Cache::store('file')->has(MenuCacheService::CACHE_KEY));
    }

    public function test_reordering_categories_persists_sort_order_and_invalidates_menu_cache(): void
    {
        $user = User::first() ?? User::factory()->create();

        MenuCacheService::get();
        $this->assertTrue(Cache::store('file')->has(MenuCacheService::CACHE_KEY));

        $categories = Category::query()->orderBy('id')->take(3)->get();
        $this->assertGreaterThanOrEqual(3, $categories->count());

        $reorderedKeys = [
            $categories[2]->id,
            $categories[0]->id,
            $categories[1]->id,
        ];

        Livewire::actingAs($user)
            ->test(ListCategories::class)
            ->call('reorderTable', $reorderedKeys)
            ->assertSuccessful();

        $this->assertEquals(1, Category::find($categories[2]->id)->sort_order);
        $this->assertEquals(2, Category::find($categories[0]->id)->sort_order);
        $this->assertEquals(3, Category::find($categories[1]->id)->sort_order);

        $this->assertFalse(Cache::store('file')->has(MenuCacheService::CACHE_KEY));
    }

    public function test_reordering_products_persists_sort_order_and_invalidates_menu_cache(): void
    {
        $user = User::first() ?? User::factory()->create();

        MenuCacheService::get();
        $this->assertTrue(Cache::store('file')->has(MenuCacheService::CACHE_KEY));

        $products = Product::query()->orderBy('id')->take(3)->get();
        $this->assertGreaterThanOrEqual(3, $products->count());

        $reorderedKeys = [
            $products[2]->id,
            $products[0]->id,
            $products[1]->id,
        ];

        Livewire::actingAs($user)
            ->test(ListProducts::class)
            ->call('reorderTable', $reorderedKeys)
            ->assertSuccessful();

        $this->assertEquals(1, Product::find($products[2]->id)->sort_order);
        $this->assertEquals(2, Product::find($products[0]->id)->sort_order);
        $this->assertEquals(3, Product::find($products[1]->id)->sort_order);

        $this->assertFalse(Cache::store('file')->has(MenuCacheService::CACHE_KEY));
    }

    public function test_public_menu_api_returns_tags_ordered_by_sort_order(): void
    {
        MenuCacheService::clear();

        $product = Product::query()->has('tags')->first();
        if (!$product) {
            $cat = Category::first();
            $product = Product::create([
                'category_id' => $cat->id,
                'name_en' => 'Sample Product for Tags',
                'name_ar' => 'منتج تجريبي للوسوم',
                'price' => 10,
                'is_active' => true,
            ]);
        }

        $tagA = Tag::where('slug', 'vegan')->first();
        $tagB = Tag::where('slug', 'gluten-free')->first();
        $tagC = Tag::where('slug', 'signature')->first();

        $tagA->update(['sort_order' => 10]);
        $tagB->update(['sort_order' => 5]);
        $tagC->update(['sort_order' => 1]);

        $product->tags()->sync([$tagA->id, $tagB->id, $tagC->id]);
        MenuCacheService::clear();

        $response = $this->getJson('/api/menu');
        $response->assertSuccessful();

        $data = $response->json('data');

        $foundTags = null;
        $search = function ($cats) use (&$search, &$foundTags, $product) {
            foreach ($cats as $c) {
                foreach ($c['products'] ?? [] as $p) {
                    if ($p['id'] == $product->id) {
                        $foundTags = $p['tags'];
                        return;
                    }
                }
                if (!empty($c['children'])) {
                    $search($c['children']);
                }
            }
        };
        $search($data);

        $this->assertNotNull($foundTags, 'Product was found in menu API');
        $slugs = collect($foundTags)->pluck('slug')->all();

        $this->assertEquals(['signature', 'gluten-free', 'vegan'], $slugs);
    }
}
