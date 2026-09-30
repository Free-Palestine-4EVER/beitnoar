<?php

namespace App\Services;

use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class MenuStaticBuilder
{
    protected static bool $needsRebuild = false;

    public function __construct(
        protected MenuService $menuService
    ) {}

    public static function shouldRebuild(): void
    {
        self::$needsRebuild = true;
    }

    public function rebuildIfNeeded(): ?array
    {
        if (self::$needsRebuild) {
            self::$needsRebuild = false;

            return $this->build();
        }

        return null;
    }

    public function build(): array
    {
        $basePath = public_path('generated/menu');
        $categoriesPath = $basePath.'/categories';
        $productsPath = $basePath.'/products';

        // Ensure directories exist
        File::ensureDirectoryExists($categoriesPath, 0755, true);
        File::ensureDirectoryExists($productsPath, 0755, true);

        try {
            // Get full menu via existing service
            $categoriesTree = $this->menuService->getFullMenu();
            $this->normalizeTreeMediaUrls($categoriesTree);

            $now = Carbon::now();
            $version = $now->format('YmdHisu');
            $generatedAt = $now->toIso8601String();

            $menuData = [
                'generated_at' => $generatedAt,
                'version' => $version,
                'categories' => $categoriesTree,
            ];

            // 1. Write full menu file
            $this->writeJsonAtomic($basePath.'/menu.json', $menuData);

            // 2. Write version file
            $this->writeJsonAtomic($basePath.'/version.json', [
                'version' => $version,
                'generated_at' => $generatedAt,
            ]);

            // 3. Process categories and products
            $processedCategories = [];
            $processedProducts = [];

            $this->processCategories($categoriesTree, $processedCategories, $processedProducts, $categoriesPath, $productsPath);

            // 4. Stale cleanup
            $this->cleanupStaleFiles($categoriesPath, $processedCategories);
            $this->cleanupStaleFiles($productsPath, $processedProducts);

            return [
                'categories_count' => count($processedCategories),
                'products_count' => count($processedProducts),
                'version' => $version,
                'generated_at' => $generatedAt,
            ];

        } catch (Exception $e) {
            Log::error('Static menu build failed: '.$e->getMessage(), [
                'exception' => $e,
            ]);
            throw $e;
        }
    }

    protected function processCategories(array $categories, array &$processedCategories, array &$processedProducts, string $categoriesPath, string $productsPath): void
    {
        foreach ($categories as $category) {
            // Write category file
            $categoryId = $category['id'];
            $this->writeJsonAtomic($categoriesPath.'/'.$categoryId.'.json', $category);
            $processedCategories[] = (string) $categoryId;

            // Process products in this category
            if (! empty($category['products'])) {
                foreach ($category['products'] as $product) {
                    $productId = $product['id'];
                    $productData = $product;
                    $productData['category_id'] = $categoryId; // Inject category_id

                    $this->writeJsonAtomic($productsPath.'/'.$productId.'.json', $productData);
                    $processedProducts[] = (string) $productId;
                }
            }

            // Process children recursively
            if (! empty($category['children'])) {
                $this->processCategories($category['children'], $processedCategories, $processedProducts, $categoriesPath, $productsPath);
            }
        }
    }

    protected function normalizeTreeMediaUrls(array &$categories): void
    {
        foreach ($categories as &$category) {
            if (! empty($category['image']) && is_string($category['image'])) {
                $pos = strpos($category['image'], '/storage/');
                if ($pos !== false) {
                    $category['image'] = substr($category['image'], $pos);
                }
            }

            if (! empty($category['products'])) {
                foreach ($category['products'] as &$product) {
                    foreach (['image_url', 'video_url', 'video_poster_url', 'model_glb_url', 'model_usdz_url'] as $field) {
                        if (! empty($product[$field]) && is_string($product[$field])) {
                            $pos = strpos($product[$field], '/storage/');
                            if ($pos !== false) {
                                $product[$field] = substr($product[$field], $pos);
                            }
                        }
                    }
                }
            }

            if (! empty($category['children'])) {
                $this->normalizeTreeMediaUrls($category['children']);
            }
        }
    }

    protected function writeJsonAtomic(string $path, array $data): void
    {
        $tmpPath = $path.'.tmp';

        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('JSON encoding failed: '.json_last_error_msg());
        }

        File::put($tmpPath, $json);

        // Validate written json
        $written = File::get($tmpPath);
        if (json_decode($written) === null && json_last_error() !== JSON_ERROR_NONE) {
            File::delete($tmpPath);
            throw new Exception('Written JSON file is invalid');
        }

        File::move($tmpPath, $path);
    }

    protected function cleanupStaleFiles(string $path, array $activeIds): void
    {
        $files = File::files($path);

        foreach ($files as $file) {
            if ($file->getExtension() === 'json') {
                $id = $file->getFilenameWithoutExtension();
                if (! in_array($id, $activeIds)) {
                    File::delete($file->getPathname());
                }
            }
        }
    }

    public function getCurrentVersion(): ?string
    {
        $versionPath = public_path('generated/menu/version.json');

        if (File::exists($versionPath)) {
            $data = json_decode(File::get($versionPath), true);

            return $data['version'] ?? null;
        }

        return null;
    }
}
