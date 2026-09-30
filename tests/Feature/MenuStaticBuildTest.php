<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Tag;
use App\Services\MenuStaticBuilder;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class MenuStaticBuildTest extends TestCase
{
    protected string $basePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->basePath = public_path('generated/menu');

        // Clean generated directory before each test
        if (File::isDirectory($this->basePath)) {
            File::deleteDirectory($this->basePath);
        }
    }

    protected function tearDown(): void
    {
        // Restore generated files from live database after tests
        if (File::isDirectory($this->basePath)) {
            File::deleteDirectory($this->basePath);
        }

        parent::tearDown();
    }

    protected function buildMenu(): array
    {
        return app(MenuStaticBuilder::class)->build();
    }

    // ------------------------------------------------------------------
    // Build output structure
    // ------------------------------------------------------------------

    public function test_build_creates_expected_files(): void
    {
        $result = $this->buildMenu();

        $this->assertFileExists($this->basePath.'/menu.json');
        $this->assertFileExists($this->basePath.'/version.json');
        $this->assertDirectoryExists($this->basePath.'/categories');
        $this->assertDirectoryExists($this->basePath.'/products');

        $this->assertIsInt($result['categories_count']);
        $this->assertIsInt($result['products_count']);
        $this->assertNotEmpty($result['version']);
        $this->assertNotEmpty($result['generated_at']);
    }

    public function test_menu_json_has_correct_structure(): void
    {
        $this->buildMenu();

        $menu = json_decode(File::get($this->basePath.'/menu.json'), true);

        $this->assertArrayHasKey('generated_at', $menu);
        $this->assertArrayHasKey('version', $menu);
        $this->assertArrayHasKey('categories', $menu);
        $this->assertIsArray($menu['categories']);
    }

    public function test_version_json_is_generated(): void
    {
        $this->buildMenu();

        $version = json_decode(File::get($this->basePath.'/version.json'), true);

        $this->assertArrayHasKey('version', $version);
        $this->assertArrayHasKey('generated_at', $version);
        $this->assertNotEmpty($version['version']);
    }

    // ------------------------------------------------------------------
    // Product update → static output changes
    // ------------------------------------------------------------------

    public function test_product_update_changes_static_output(): void
    {
        $this->buildMenu();

        $product = Product::where('is_active', true)->first();
        $this->assertNotNull($product, 'No active product found in database');

        $originalJson = File::get($this->basePath.'/products/'.$product->id.'.json');
        $originalData = json_decode($originalJson, true);

        $newPrice = (float) $originalData['price'] + 1.0;
        $product->update(['price' => $newPrice]);
        $this->buildMenu();

        $updatedData = json_decode(File::get($this->basePath.'/products/'.$product->id.'.json'), true);
        $this->assertEquals($newPrice, $updatedData['price']);

        // Restore original price
        $product->update(['price' => $originalData['price']]);
    }

    // ------------------------------------------------------------------
    // Category rename → static output changes
    // ------------------------------------------------------------------

    public function test_category_rename_changes_static_output(): void
    {
        $this->buildMenu();

        $category = Category::where('is_active', true)->first();
        $this->assertNotNull($category);

        $originalName = $category->name_en;
        $category->update(['name_en' => 'Renamed Test Category']);
        $this->buildMenu();

        $catData = json_decode(File::get($this->basePath.'/categories/'.$category->id.'.json'), true);
        $this->assertEquals('Renamed Test Category', $catData['name_en']);

        // Also verify it changed in menu.json
        $menuData = json_decode(File::get($this->basePath.'/menu.json'), true);
        $found = $this->findInTree($menuData['categories'], $category->id);
        $this->assertNotNull($found);
        $this->assertEquals('Renamed Test Category', $found['name_en']);

        // Restore
        $category->update(['name_en' => $originalName]);
    }

    // ------------------------------------------------------------------
    // Sorting
    // ------------------------------------------------------------------

    public function test_category_reorder_changes_static_order(): void
    {
        $this->buildMenu();

        $menuBefore = json_decode(File::get($this->basePath.'/menu.json'), true);
        $firstCatBefore = $menuBefore['categories'][0]['id'] ?? null;

        // Create a category with sort_order -1 to force it first
        $newCat = Category::create([
            'name_en' => 'Sort Test Category',
            'name_ar' => 'فئة ترتيب',
            'is_active' => true,
            'sort_order' => -1,
        ]);

        $this->buildMenu();

        $menuAfter = json_decode(File::get($this->basePath.'/menu.json'), true);
        $this->assertEquals($newCat->id, $menuAfter['categories'][0]['id']);

        $newCat->delete();
    }

    public function test_product_reorder_changes_static_order(): void
    {
        $category = Category::whereHas('products', function ($q) {
            $q->where('is_active', true);
        })->first();
        $this->assertNotNull($category);

        $products = Product::where('category_id', $category->id)
            ->where('is_active', true)
            ->sorted()
            ->get();

        if ($products->count() < 2) {
            $this->markTestSkipped('Need at least 2 active products in a category');
        }

        $this->buildMenu();

        $catData = json_decode(File::get($this->basePath.'/categories/'.$category->id.'.json'), true);
        $productIds = array_column($catData['products'] ?? [], 'id');
        $this->assertCount($products->count(), $productIds);

        // Swap first two products' sort order
        $first = $products[0];
        $second = $products[1];
        $origFirstSort = $first->sort_order;
        $origSecondSort = $second->sort_order;

        $first->update(['sort_order' => $origSecondSort + 1000]);
        $second->update(['sort_order' => $origFirstSort]);

        $this->buildMenu();

        $catDataAfter = json_decode(File::get($this->basePath.'/categories/'.$category->id.'.json'), true);
        $productIdsAfter = array_column($catDataAfter['products'] ?? [], 'id');

        $this->assertNotEquals($productIds, $productIdsAfter, 'Product order should have changed');

        // Restore
        $first->update(['sort_order' => $origFirstSort]);
        $second->update(['sort_order' => $origSecondSort]);
    }

    // ------------------------------------------------------------------
    // Tags
    // ------------------------------------------------------------------

    public function test_tag_update_changes_product_static_json(): void
    {
        $tag = Tag::factory()->create(['name_en' => 'Test Tag Original']);
        $product = Product::where('is_active', true)->first();
        $product->tags()->attach($tag);

        $this->buildMenu();

        $productData = json_decode(File::get($this->basePath.'/products/'.$product->id.'.json'), true);
        $tagNames = array_column($productData['tags'], 'name_en');
        $this->assertContains('Test Tag Original', $tagNames);

        $tag->update(['name_en' => 'Test Tag Updated']);
        $this->buildMenu();

        $productDataAfter = json_decode(File::get($this->basePath.'/products/'.$product->id.'.json'), true);
        $tagNamesAfter = array_column($productDataAfter['tags'], 'name_en');
        $this->assertContains('Test Tag Updated', $tagNamesAfter);
        $this->assertNotContains('Test Tag Original', $tagNamesAfter);

        // Cleanup
        $product->tags()->detach($tag);
        $tag->delete();
    }

    public function test_product_tags_sync_changes_static_json(): void
    {
        $tag1 = Tag::factory()->create(['name_en' => 'Sync Tag A']);
        $tag2 = Tag::factory()->create(['name_en' => 'Sync Tag B']);
        $product = Product::where('is_active', true)->first();

        $product->tags()->sync([$tag1->id]);
        $this->buildMenu();

        $productData = json_decode(File::get($this->basePath.'/products/'.$product->id.'.json'), true);
        $tagNames = array_column($productData['tags'], 'name_en');
        $this->assertContains('Sync Tag A', $tagNames);
        $this->assertNotContains('Sync Tag B', $tagNames);

        // Now sync to tag2 only
        $product->tags()->sync([$tag2->id]);
        $this->buildMenu();

        $productDataAfter = json_decode(File::get($this->basePath.'/products/'.$product->id.'.json'), true);
        $tagNamesAfter = array_column($productDataAfter['tags'], 'name_en');
        $this->assertContains('Sync Tag B', $tagNamesAfter);
        $this->assertNotContains('Sync Tag A', $tagNamesAfter);

        // Cleanup
        $product->tags()->detach();
        $tag1->delete();
        $tag2->delete();
    }

    // ------------------------------------------------------------------
    // Active / Inactive
    // ------------------------------------------------------------------

    public function test_inactive_product_is_excluded_and_file_removed(): void
    {
        $category = Category::where('is_active', true)->first();
        $product = Product::factory()->forCategory($category)->create([
            'is_active' => true,
            'sort_order' => 9999,
        ]);

        $this->buildMenu();
        $this->assertFileExists($this->basePath.'/products/'.$product->id.'.json');

        $product->update(['is_active' => false]);
        $this->buildMenu();

        $this->assertFileDoesNotExist($this->basePath.'/products/'.$product->id.'.json');

        // Also check menu.json doesn't include it
        $menu = json_decode(File::get($this->basePath.'/menu.json'), true);
        $foundProduct = $this->findProductInTree($menu['categories'], $product->id);
        $this->assertNull($foundProduct);

        $product->forceDelete();
    }

    public function test_inactive_category_is_excluded(): void
    {
        $category = Category::factory()->create([
            'is_active' => true,
            'sort_order' => 9999,
        ]);

        $this->buildMenu();
        $this->assertFileExists($this->basePath.'/categories/'.$category->id.'.json');

        $category->update(['is_active' => false]);
        $this->buildMenu();

        $this->assertFileDoesNotExist($this->basePath.'/categories/'.$category->id.'.json');

        $menu = json_decode(File::get($this->basePath.'/menu.json'), true);
        $found = $this->findInTree($menu['categories'], $category->id);
        $this->assertNull($found);

        $category->delete();
    }

    // ------------------------------------------------------------------
    // Nested categories
    // ------------------------------------------------------------------

    public function test_nested_categories_are_correctly_structured(): void
    {
        $parent = Category::factory()->create([
            'name_en' => 'Test Parent',
            'sort_order' => 9999,
        ]);
        $section = Category::factory()->withParent($parent)->create([
            'name_en' => 'Test Section',
            'sort_order' => 0,
        ]);
        $subsection = Category::factory()->withParent($section)->create([
            'name_en' => 'Test Subsection',
            'sort_order' => 0,
        ]);
        $product = Product::factory()->forCategory($subsection)->create([
            'name_en' => 'Nested Product',
            'sort_order' => 0,
        ]);

        $this->buildMenu();

        // Check menu.json has the nested structure
        $menu = json_decode(File::get($this->basePath.'/menu.json'), true);
        $parentData = $this->findInTree($menu['categories'], $parent->id);
        $this->assertNotNull($parentData, 'Parent category not found');
        $this->assertEquals('Test Parent', $parentData['name_en']);

        // Check section is nested under parent
        $sectionData = collect($parentData['children'])->firstWhere('id', $section->id);
        $this->assertNotNull($sectionData, 'Section not found under parent');

        // Check subsection is nested under section
        $subsectionData = collect($sectionData['children'])->firstWhere('id', $subsection->id);
        $this->assertNotNull($subsectionData, 'Subsection not found under section');

        // Check product is in subsection
        $productData = collect($subsectionData['products'])->firstWhere('id', $product->id);
        $this->assertNotNull($productData, 'Product not found in subsection');
        $this->assertEquals('Nested Product', $productData['name_en']);

        // Individual files exist
        $this->assertFileExists($this->basePath.'/categories/'.$parent->id.'.json');
        $this->assertFileExists($this->basePath.'/categories/'.$section->id.'.json');
        $this->assertFileExists($this->basePath.'/categories/'.$subsection->id.'.json');
        $this->assertFileExists($this->basePath.'/products/'.$product->id.'.json');

        // Product file has category_id
        $prodFile = json_decode(File::get($this->basePath.'/products/'.$product->id.'.json'), true);
        $this->assertEquals($subsection->id, $prodFile['category_id']);

        // Cleanup
        $product->forceDelete();
        $subsection->delete();
        $section->delete();
        $parent->delete();
    }

    // ------------------------------------------------------------------
    // Artisan command
    // ------------------------------------------------------------------

    public function test_artisan_command_returns_success(): void
    {
        $this->artisan('menu:build-static')
            ->assertExitCode(0);

        $this->assertFileExists($this->basePath.'/menu.json');
    }

    // ------------------------------------------------------------------
    // Debounce
    // ------------------------------------------------------------------

    public function test_should_rebuild_debounces_multiple_calls(): void
    {
        MenuStaticBuilder::shouldRebuild();
        MenuStaticBuilder::shouldRebuild();
        MenuStaticBuilder::shouldRebuild();

        $builder = app(MenuStaticBuilder::class);
        $result = $builder->rebuildIfNeeded();

        $this->assertNotNull($result, 'First call should rebuild');

        $resultSecond = $builder->rebuildIfNeeded();
        $this->assertNull($resultSecond, 'Second call should not rebuild');
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * Find a category by ID in the nested tree.
     *
     * @param  array<int, array<string, mixed>>  $categories
     * @return array<string, mixed>|null
     */
    protected function findInTree(array $categories, int $id): ?array
    {
        foreach ($categories as $cat) {
            if ($cat['id'] === $id) {
                return $cat;
            }
            if (! empty($cat['children'])) {
                $found = $this->findInTree($cat['children'], $id);
                if ($found) {
                    return $found;
                }
            }
        }

        return null;
    }

    /**
     * Find a product by ID in the nested tree.
     *
     * @param  array<int, array<string, mixed>>  $categories
     * @return array<string, mixed>|null
     */
    protected function findProductInTree(array $categories, int $id): ?array
    {
        foreach ($categories as $cat) {
            if (! empty($cat['products'])) {
                foreach ($cat['products'] as $product) {
                    if ($product['id'] === $id) {
                        return $product;
                    }
                }
            }
            if (! empty($cat['children'])) {
                $found = $this->findProductInTree($cat['children'], $id);
                if ($found) {
                    return $found;
                }
            }
        }

        return null;
    }

    /**
     * Extract product IDs from a category data array (including children).
     *
     * @param  array<string, mixed>  $categoryData
     * @return array<int>
     */
    protected function extractProductIds(array $categoryData): array
    {
        $ids = [];
        if (! empty($categoryData['products'])) {
            foreach ($categoryData['products'] as $product) {
                $ids[] = $product['id'];
            }
        }
        if (! empty($categoryData['children'])) {
            foreach ($categoryData['children'] as $child) {
                $ids = array_merge($ids, $this->extractProductIds($child));
            }
        }

        return $ids;
    }
}
