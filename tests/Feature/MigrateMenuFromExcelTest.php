<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Tag;
use App\Services\MenuCacheService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class MigrateMenuFromExcelTest extends TestCase
{
    public function test_command_fails_if_file_does_not_exist(): void
    {
        $exitCode = Artisan::call('app:migrate-menu-from-excel', [
            'file' => '/path/to/nonexistent/file.xlsx',
        ]);

        $this->assertEquals(1, $exitCode);
    }

    public function test_command_successfully_truncates_and_migrates_excel(): void
    {
        // Prime cache before running command
        MenuCacheService::get();
        $this->assertTrue(Cache::store('file')->has(MenuCacheService::CACHE_KEY));

        $exitCode = Artisan::call('app:migrate-menu-from-excel');
        $this->assertEquals(0, $exitCode);

        // Verify cache was cleared
        $this->assertFalse(Cache::store('file')->has(MenuCacheService::CACHE_KEY));

        // Verify root category
        $rootCategories = Category::whereNull('parent_id')->get();
        $this->assertCount(1, $rootCategories);
        $root = $rootCategories->first();
        $this->assertEquals("Elia's Chef", $root->name_en);
        $this->assertEquals('شيف إيليا', $root->name_ar);

        // Verify subcategories
        $subcategories = Category::where('parent_id', $root->id)->get();
        $this->assertCount(11, $subcategories);

        $subNamesEn = $subcategories->pluck('name_en')->all();
        $this->assertContains('Breakfast Platters', $subNamesEn);
        $this->assertContains('From the Oven', $subNamesEn);
        $this->assertContains('Soups', $subNamesEn);
        $this->assertContains('Salads', $subNamesEn);
        $this->assertContains('Cold Mezze', $subNamesEn);
        $this->assertContains('Hot Mezze', $subNamesEn);
        $this->assertContains('Nayyeh', $subNamesEn);
        $this->assertContains('On the Charcoal', $subNamesEn);
        $this->assertContains('Main Courses', $subNamesEn);
        $this->assertContains('Fakharet', $subNamesEn);
        $this->assertContains('Sandwiches', $subNamesEn);

        // Verify total products
        $this->assertEquals(111, Product::count());

        // Verify product counts per subcategory
        $breakfast = Category::where('name_en', 'Breakfast Platters')->first();
        $this->assertEquals(32, $breakfast->products()->count());

        $fakharet = Category::where('name_en', 'Fakharet')->first();
        $this->assertEquals(3, $fakharet->products()->count());

        $charcoal = Category::where('name_en', 'On the Charcoal')->first();
        $this->assertEquals(14, $charcoal->products()->count());

        // Verify Nayyeh items have raw tag
        $rawTag = Tag::where('slug', 'raw')->first();
        if ($rawTag) {
            $nayyehCat = Category::where('name_en', 'Nayyeh')->first();
            $this->assertEquals(3, $nayyehCat->products()->count());
            foreach ($nayyehCat->products as $nayyehProduct) {
                $this->assertTrue($nayyehProduct->tags->contains('id', $rawTag->id));
            }
        }

        // Verify featured item
        $featuredItem = Product::where('name_en', 'Sautéed Chicken Liver')->first();
        $this->assertNotNull($featuredItem);
        $this->assertTrue($featuredItem->is_featured);

        // Verify menu API returns the new menu cleanly
        $response = $this->getJson('/api/menu');
        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals("Elia's Chef", $data[0]['name_en']);
        $this->assertCount(11, $data[0]['children']);
    }
}
