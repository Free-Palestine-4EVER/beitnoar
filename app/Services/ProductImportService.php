<?php

namespace App\Services;

use App\Exports\ProductImportErrorsExport;
use App\Models\Category;
use App\Models\Product;
use App\Models\Tag;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ProductImportService
{
    protected array $categories = [];
    protected array $tags = [];
    protected array $maxSortOrderPerCategory = [];
    protected array $existingProducts = [];

    public function __construct()
    {
        $this->preloadData();
    }

    protected function preloadData(): void
    {
        // Preload categories by lowercase trimmed name_en
        $this->categories = Category::all()->keyBy(fn ($c) => strtolower(trim($c->name_en)))->all();

        // Preload tags by lowercase slug
        $this->tags = Tag::all()->keyBy(fn ($t) => strtolower(trim($t->slug)))->all();

        // Preload max sort_order per category
        $maxSorts = Product::query()
            ->select('category_id', DB::raw('MAX(sort_order) as max_sort'))
            ->groupBy('category_id')
            ->pluck('max_sort', 'category_id')
            ->toArray();
        $this->maxSortOrderPerCategory = $maxSorts;

        // Preload existing products: category_id . '::' . strtolower(trim(name_en))
        $products = Product::all(['id', 'category_id', 'name_en']);
        foreach ($products as $prod) {
            $key = $prod->category_id . '::' . strtolower(trim($prod->name_en));
            $this->existingProducts[$key] = true;
        }
    }

    public function import(string $filePath): array
    {
        // Load rows using PhpSpreadsheet directly to prevent formula execution and handle sheets safely
        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getSheetByName('Products') ?? $spreadsheet->getActiveSheet();
        $rawRows = $sheet->toArray(null, false, false, true);

        if (empty($rawRows)) {
            return [
                'success' => false,
                'imported_count' => 0,
                'errors' => [
                    ['row' => 1, 'product' => '-', 'error' => 'Spreadsheet is empty.'],
                ],
                'error_file_url' => null,
            ];
        }

        // Get header keys from first row
        $headerRow = array_shift($rawRows);
        $headers = [];
        foreach ($headerRow as $colLetter => $headerName) {
            if ($headerName !== null && trim((string)$headerName) !== '') {
                $headers[$colLetter] = strtolower(trim((string)$headerName));
            }
        }

        $expectedColumns = [
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

        // Map column names to column letters
        $colMap = [];
        foreach ($headers as $letter => $name) {
            $colMap[$name] = $letter;
        }

        foreach (['category', 'name_en', 'name_ar', 'price'] as $reqCol) {
            if (!isset($colMap[$reqCol])) {
                return [
                    'success' => false,
                    'imported_count' => 0,
                    'errors' => [
                        ['row' => 1, 'product' => '-', 'error' => "Required column header '{$reqCol}' is missing from Products sheet."],
                    ],
                    'error_file_url' => null,
                ];
            }
        }

        $parsedRows = [];
        $errors = [];
        $seenInSheet = [];
        $rowNumber = 1; // 1 was header

        foreach ($rawRows as $row) {
            $rowNumber++;

            // Extract row data
            $rowData = [];
            $hasAnyValue = false;
            foreach ($expectedColumns as $colName) {
                $letter = $colMap[$colName] ?? null;
                $val = $letter !== null && isset($row[$letter]) ? $row[$letter] : null;
                if ($val !== null && trim((string)$val) !== '') {
                    $hasAnyValue = true;
                    $rowData[$colName] = is_string($val) ? trim($val) : $val;
                } else {
                    $rowData[$colName] = null;
                }
            }

            // Rule 8: Ignore completely empty rows
            if (!$hasAnyValue) {
                continue;
            }

            $productName = $rowData['name_en'] ?? ("Row {$rowNumber}");

            // Validate Category (Rule 5)
            $catName = $rowData['category'];
            if (!$catName) {
                $errors[] = [
                    'row' => $rowNumber,
                    'product' => $productName,
                    'error' => "Category is required.",
                ];
                continue;
            }
            $catKey = strtolower(trim($catName));
            if (!isset($this->categories[$catKey])) {
                $errors[] = [
                    'row' => $rowNumber,
                    'product' => $productName,
                    'error' => "Category \"{$catName}\" does not exist.",
                ];
                continue;
            }
            $category = $this->categories[$catKey];

            // Validate name_en & name_ar (Rule 7)
            if (empty($rowData['name_en'])) {
                $errors[] = [
                    'row' => $rowNumber,
                    'product' => $productName,
                    'error' => "English product name (name_en) is required.",
                ];
                continue;
            }
            if (empty($rowData['name_ar'])) {
                $errors[] = [
                    'row' => $rowNumber,
                    'product' => $productName,
                    'error' => "Arabic product name (name_ar) is required.",
                ];
                continue;
            }

            // Validate Duplicate in Database & Sheet (Rule 15)
            $productKey = $category->id . '::' . strtolower(trim($rowData['name_en']));
            if (isset($this->existingProducts[$productKey])) {
                $errors[] = [
                    'row' => $rowNumber,
                    'product' => $rowData['name_en'],
                    'error' => "Product \"{$rowData['name_en']}\" already exists in category \"{$category->name_en}\".",
                ];
                continue;
            }
            if (isset($seenInSheet[$productKey])) {
                $errors[] = [
                    'row' => $rowNumber,
                    'product' => $rowData['name_en'],
                    'error' => "Duplicate product \"{$rowData['name_en']}\" found multiple times in this Excel file for category \"{$category->name_en}\".",
                ];
                continue;
            }
            $seenInSheet[$productKey] = true;

            // Validate Price (Rule 7 & 10)
            $priceRaw = $rowData['price'];
            if ($priceRaw === null || !is_numeric($priceRaw) || (float)$priceRaw < 0) {
                $errors[] = [
                    'row' => $rowNumber,
                    'product' => $rowData['name_en'],
                    'error' => "Price must be a valid non-negative number.",
                ];
                continue;
            }
            $price = round((float)$priceRaw, 2);

            // Validate Calories
            $calories = null;
            if ($rowData['calories'] !== null) {
                if (!is_numeric($rowData['calories']) || (int)$rowData['calories'] < 0) {
                    $errors[] = [
                        'row' => $rowNumber,
                        'product' => $rowData['name_en'],
                        'error' => "Calories must be a non-negative integer.",
                    ];
                    continue;
                }
                $calories = (int)$rowData['calories'];
            }

            // Validate Booleans: is_active & is_featured (Rule 9)
            $isActive = $this->parseBoolean($rowData['is_active'], true);
            if ($isActive === null) {
                $errors[] = [
                    'row' => $rowNumber,
                    'product' => $rowData['name_en'],
                    'error' => "Invalid value for is_active. Expected 1, 0, yes, no, true, or false.",
                ];
                continue;
            }

            $isFeatured = $this->parseBoolean($rowData['is_featured'], false);
            if ($isFeatured === null) {
                $errors[] = [
                    'row' => $rowNumber,
                    'product' => $rowData['name_en'],
                    'error' => "Invalid value for is_featured. Expected 1, 0, yes, no, true, or false.",
                ];
                continue;
            }

            // Validate Tags (Rule 6)
            $tagIds = [];
            if (!empty($rowData['tags'])) {
                $rawTags = explode(',', (string)$rowData['tags']);
                $tagErrors = [];
                foreach ($rawTags as $rawTag) {
                    $cleanSlug = strtolower(trim($rawTag));
                    if ($cleanSlug === '') continue;
                    if (!isset($this->tags[$cleanSlug])) {
                        $tagErrors[] = "Unknown tag \"{$cleanSlug}\".";
                    } else {
                        $tagIds[] = $this->tags[$cleanSlug]->id;
                    }
                }
                if (!empty($tagErrors)) {
                    $errors[] = [
                        'row' => $rowNumber,
                        'product' => $rowData['name_en'],
                        'error' => implode(' ', $tagErrors),
                    ];
                    continue;
                }
            }

            // Validate Image URL (Rule 11)
            $imageUrl = null;
            if (!empty($rowData['image_url'])) {
                if (!filter_var($rowData['image_url'], FILTER_VALIDATE_URL)) {
                    $errors[] = [
                        'row' => $rowNumber,
                        'product' => $rowData['name_en'],
                        'error' => "Image URL is invalid: \"{$rowData['image_url']}\".",
                    ];
                    continue;
                }
                $imageUrl = $rowData['image_url'];
            }

            // Sort order (Rule 16)
            $sortOrder = null;
            if ($rowData['sort_order'] !== null && is_numeric($rowData['sort_order'])) {
                $sortOrder = (int)$rowData['sort_order'];
            } else {
                // Determine next sort order within that category
                $currentMax = $this->maxSortOrderPerCategory[$category->id] ?? 0;
                $sortOrder = $currentMax + 1;
                $this->maxSortOrderPerCategory[$category->id] = $sortOrder;
            }

            $parsedRows[] = [
                'row_number' => $rowNumber,
                'category_id' => $category->id,
                'name_en' => (string)$rowData['name_en'],
                'name_ar' => (string)$rowData['name_ar'],
                'description_en' => $rowData['description_en'] ? (string)$rowData['description_en'] : null,
                'description_ar' => $rowData['description_ar'] ? (string)$rowData['description_ar'] : null,
                'price' => $price,
                'calories' => $calories,
                'is_active' => $isActive,
                'is_featured' => $isFeatured,
                'sort_order' => $sortOrder,
                'image_url' => $imageUrl,
                'tag_ids' => $tagIds,
            ];
        }

        // Rule 14: All-or-nothing transaction safety. If ANY row is invalid, import zero products.
        if (!empty($errors)) {
            $errorFileUrl = $this->generateErrorReport($errors);

            return [
                'success' => false,
                'imported_count' => 0,
                'errors' => $errors,
                'error_file_url' => $errorFileUrl,
            ];
        }

        if (empty($parsedRows)) {
            return [
                'success' => false,
                'imported_count' => 0,
                'errors' => [
                    ['row' => 1, 'product' => '-', 'error' => 'No valid data rows found to import.'],
                ],
                'error_file_url' => null,
            ];
        }

        // Now download images (if any) and persist inside a DB transaction
        $downloadedImages = [];
        foreach ($parsedRows as &$pRow) {
            if ($pRow['image_url']) {
                $storedPath = $this->downloadAndStoreImage($pRow['image_url']);
                if ($storedPath === false) {
                    $errors[] = [
                        'row' => $pRow['row_number'],
                        'product' => $pRow['name_en'],
                        'error' => "Failed to download image from \"{$pRow['image_url']}\" (must be jpg, jpeg, png, webp <= 5MB).",
                    ];
                } else {
                    $pRow['stored_image'] = $storedPath;
                    $downloadedImages[] = $storedPath;
                }
            } else {
                $pRow['stored_image'] = null;
            }
        }
        unset($pRow);

        if (!empty($errors)) {
            // Clean up any images downloaded during this run
            foreach ($downloadedImages as $img) {
                Storage::disk('public')->delete($img);
            }

            $errorFileUrl = $this->generateErrorReport($errors);

            return [
                'success' => false,
                'imported_count' => 0,
                'errors' => $errors,
                'error_file_url' => $errorFileUrl,
            ];
        }

        // Execute DB Transaction
        DB::transaction(function () use ($parsedRows) {
            foreach ($parsedRows as $pData) {
                $product = Product::create([
                    'category_id' => $pData['category_id'],
                    'name_en' => $pData['name_en'],
                    'name_ar' => $pData['name_ar'],
                    'description_en' => $pData['description_en'],
                    'description_ar' => $pData['description_ar'],
                    'price' => $pData['price'],
                    'calories' => $pData['calories'],
                    'image' => $pData['stored_image'],
                    'is_active' => $pData['is_active'],
                    'is_featured' => $pData['is_featured'],
                    'sort_order' => $pData['sort_order'],
                ]);

                if (!empty($pData['tag_ids'])) {
                    $product->tags()->sync($pData['tag_ids']);
                }
            }
        });

        // Rule 17: Invalidate menu cache once
        MenuCacheService::clear();

        return [
            'success' => true,
            'imported_count' => count($parsedRows),
            'errors' => [],
            'error_file_url' => null,
        ];
    }

    protected function parseBoolean(mixed $value, bool $default): ?bool
    {
        if ($value === null || $value === '') {
            return $default;
        }

        if (is_bool($value)) {
            return $value;
        }

        $str = strtolower(trim((string)$value));

        if (in_array($str, ['1', 'true', 'yes'], true)) {
            return true;
        }

        if (in_array($str, ['0', 'false', 'no'], true)) {
            return false;
        }

        return null;
    }

    protected function downloadAndStoreImage(string $url): string|false
    {
        try {
            $response = Http::timeout(10)->get($url);

            if (!$response->successful()) {
                return false;
            }

            $contentType = strtolower($response->header('Content-Type') ?? '');
            $extension = null;

            if (str_contains($contentType, 'jpeg') || str_contains($contentType, 'jpg')) {
                $extension = 'jpg';
            } elseif (str_contains($contentType, 'png')) {
                $extension = 'png';
            } elseif (str_contains($contentType, 'webp')) {
                $extension = 'webp';
            } else {
                // Fallback check URL path extension
                $path = parse_url($url, PHP_URL_PATH);
                $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                    $extension = $ext === 'jpeg' ? 'jpg' : $ext;
                }
            }

            if (!$extension) {
                return false;
            }

            // Max size: 5MB
            $body = $response->body();
            if (strlen($body) > 5 * 1024 * 1024) {
                return false;
            }

            $filename = 'products/' . Str::random(40) . '.' . $extension;
            Storage::disk('public')->put($filename, $body);

            return $filename;
        } catch (\Throwable $e) {
            return false;
        }
    }

    protected function generateErrorReport(array $errors): ?string
    {
        try {
            $formatted = [];
            foreach ($errors as $err) {
                $formatted[] = [
                    'row' => $err['row'],
                    'product' => $err['product'],
                    'error' => $err['error'],
                ];
            }

            $fileName = 'products_import_errors_' . time() . '.xlsx';
            $filePath = 'temp/' . $fileName;

            Excel::store(new ProductImportErrorsExport($formatted), $filePath, 'public');

            return Storage::disk('public')->url($filePath);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
