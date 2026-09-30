<?php

namespace Tests\Feature;

use App\Exports\ProductImportTemplateExport;
use App\Models\Category;
use App\Models\Product;
use App\Models\Tag;
use App\Models\User;
use App\Services\MenuCacheService;
use App\Services\ProductImportService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Cache;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ProductExcelImportTest extends TestCase
{
    protected function createExcelFile(array $rows, string $sheetName = 'Products'): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($sheetName);

        $headers = [
            'category',
            'name_en',
            'name_ar',
            'description_en',
            'description_ar',
            'price',
            'calories',
            'tags',
            'is_active',
            'is_featured',
            'sort_order',
            'image_url',
        ];

        $sheet->fromArray([$headers], null, 'A1');

        if (!empty($rows)) {
            $sheet->fromArray($rows, null, 'A2');
        }

        $tempFile = tempnam(sys_get_temp_dir(), 'test_import_') . '.xlsx';
        $writer = new Xlsx($spreadsheet);
        $writer->save($tempFile);

        return $tempFile;
    }

    public function test_template_export_generates_correct_sheets_and_headings(): void
    {
        $export = new ProductImportTemplateExport();
        $sheets = $export->sheets();

        $this->assertArrayHasKey('Products', $sheets);
        $this->assertArrayHasKey('Instructions', $sheets);

        $productsSheet = $sheets['Products'];
        $this->assertEquals([
            'category',
            'name_en',
            'name_ar',
            'description_en',
            'description_ar',
            'price',
            'calories',
            'tags',
            'is_active',
            'is_featured',
            'sort_order',
            'image_url',
        ], $productsSheet->headings());

        $this->assertNotEmpty($productsSheet->array());
    }

    public function test_download_template_action_returns_successful_download(): void
    {
        $user = User::first() ?? User::factory()->create();

        \Livewire\Livewire::actingAs($user)
            ->test(\App\Filament\Resources\Products\Pages\ListProducts::class)
            ->callAction('downloadTemplate')
            ->assertFileDownloaded('products_import_template.xlsx');
    }

    public function test_valid_excel_imports_products_attaches_tags_and_clears_cache(): void
    {
        $category = Category::first();
        $this->assertNotNull($category);

        // Prime cache
        MenuCacheService::get();
        $this->assertTrue(Cache::store('file')->has(MenuCacheService::CACHE_KEY));

        $uniqueSuffix = time() . '_' . rand(100, 999);
        $testRows = [
            [
                $category->name_en,
                'Imported Dish A ' . $uniqueSuffix,
                'طبق مستورد أ',
                'Delicious imported dish.',
                'طبق مستورد لذيذ.',
                '7.50',
                '450',
                'vegan,gluten-free',
                'yes',
                '1',
                '10',
                '',
            ],
            [
                $category->name_en,
                'Imported Dish B ' . $uniqueSuffix,
                'طبق مستورد ب',
                'Another imported dish.',
                'طبق آخر مستورد.',
                '9.00',
                '',
                'signature',
                '1',
                '0',
                '', // empty sort order to test auto-generation
                '',
            ],
        ];

        $filePath = $this->createExcelFile($testRows);

        $service = new ProductImportService();
        $result = $service->import($filePath);
        @unlink($filePath);

        $this->assertTrue($result['success']);
        $this->assertEquals(2, $result['imported_count']);
        $this->assertEmpty($result['errors']);

        // Cache must be cleared
        $this->assertFalse(Cache::store('file')->has(MenuCacheService::CACHE_KEY));

        // Check products in DB
        $dishA = Product::where('name_en', 'Imported Dish A ' . $uniqueSuffix)->first();
        $this->assertNotNull($dishA);
        $this->assertEquals(7.50, $dishA->price);
        $this->assertEquals(450, $dishA->calories);
        $this->assertTrue($dishA->is_active);
        $this->assertTrue($dishA->is_featured);
        $this->assertEquals(10, $dishA->sort_order);
        $this->assertEquals(['gluten-free', 'vegan'], $dishA->tags()->pluck('slug')->sort()->values()->all());

        $dishB = Product::where('name_en', 'Imported Dish B ' . $uniqueSuffix)->first();
        $this->assertNotNull($dishB);
        $this->assertEquals(9.00, $dishB->price);
        $this->assertNull($dishB->calories);
        $this->assertTrue($dishB->is_active);
        $this->assertFalse($dishB->is_featured);
        $this->assertGreaterThan(0, $dishB->sort_order);
        $this->assertEquals(['signature'], $dishB->tags()->pluck('slug')->all());
    }

    public function test_import_fails_on_invalid_category_and_imports_zero_products(): void
    {
        $uniqueName = 'Should Not Exist ' . time();
        $testRows = [
            [
                'NonExistentCategoryXYZ',
                $uniqueName,
                'طبق غير موجود',
                'desc',
                'وصف',
                '5.00',
                '300',
                '',
                '1',
                '0',
                '1',
                '',
            ],
        ];

        $filePath = $this->createExcelFile($testRows);

        $service = new ProductImportService();
        $result = $service->import($filePath);
        @unlink($filePath);

        $this->assertFalse($result['success']);
        $this->assertEquals(0, $result['imported_count']);
        $this->assertNotEmpty($result['errors']);
        $this->assertStringContainsString('does not exist', $result['errors'][0]['error']);

        $this->assertNull(Product::where('name_en', $uniqueName)->first());
    }

    public function test_import_fails_on_invalid_tag(): void
    {
        $category = Category::first();
        $uniqueName = 'Invalid Tag Dish ' . time();
        $testRows = [
            [
                $category->name_en,
                $uniqueName,
                'طبق تاغ خاطئ',
                'desc',
                'وصف',
                '5.00',
                '300',
                'unknown-fake-tag-123',
                '1',
                '0',
                '1',
                '',
            ],
        ];

        $filePath = $this->createExcelFile($testRows);

        $service = new ProductImportService();
        $result = $service->import($filePath);
        @unlink($filePath);

        $this->assertFalse($result['success']);
        $this->assertEquals(0, $result['imported_count']);
        $this->assertStringContainsString('Unknown tag', $result['errors'][0]['error']);
    }

    public function test_import_fails_on_invalid_price(): void
    {
        $category = Category::first();
        $uniqueName = 'Invalid Price Dish ' . time();
        $testRows = [
            [
                $category->name_en,
                $uniqueName,
                'طبق سعر خاطئ',
                'desc',
                'وصف',
                'JOD 5.00', // Invalid formatted text instead of numeric
                '300',
                '',
                '1',
                '0',
                '1',
                '',
            ],
        ];

        $filePath = $this->createExcelFile($testRows);

        $service = new ProductImportService();
        $result = $service->import($filePath);
        @unlink($filePath);

        $this->assertFalse($result['success']);
        $this->assertEquals(0, $result['imported_count']);
        $this->assertStringContainsString('Price must be a valid', $result['errors'][0]['error']);
    }

    public function test_import_rejects_duplicate_product_within_category(): void
    {
        $existing = Product::first();
        $category = $existing->category;

        $testRows = [
            [
                $category->name_en,
                $existing->name_en,
                'اسم مكرر',
                'desc',
                'وصف',
                '5.00',
                '300',
                '',
                '1',
                '0',
                '1',
                '',
            ],
        ];

        $filePath = $this->createExcelFile($testRows);

        $service = new ProductImportService();
        $result = $service->import($filePath);
        @unlink($filePath);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('already exists in category', $result['errors'][0]['error']);
    }
}
