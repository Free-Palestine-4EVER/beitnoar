<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Product;
use App\Models\Tag;
use App\Services\MenuCacheService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\IOFactory;

class MigrateMenuFromExcel extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:migrate-menu-from-excel {file? : Path to the Excel menu file}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove existing products and categories and migrate menue.xlsx into products and categories';

    /**
     * Execute the console command.
     */
    protected static array $arModelsMap = [
        'Alayet Banadoura' => ['glb' => 'models/dish_024.glb', 'usdz' => 'models/dish_024.usdz'],
        'Arayes Kafta' => ['glb' => 'models/dish_100.glb', 'usdz' => 'models/dish_100.usdz'],
        'Bamyeh Bi Zeit' => ['glb' => 'models/dish_062.glb', 'usdz' => 'models/dish_062.usdz'],
        'Baqlawah' => ['glb' => 'models/dish_158.glb', 'usdz' => 'models/dish_158.usdz'],
        'Batinjan Raheb' => ['glb' => 'models/dish_063.glb', 'usdz' => 'models/dish_063.usdz'],
        'Beef Shawarma Platter' => ['glb' => 'models/dish_101.glb', 'usdz' => 'models/dish_101.usdz'],
        'Beef Shawarma Sandwich' => ['glb' => 'models/dish_109.glb', 'usdz' => 'models/dish_109.usdz'],
        'Carrot & Pumpkin Soup' => ['glb' => 'models/dish_044.glb', 'usdz' => 'models/dish_044.usdz'],
        'Cheese' => ['glb' => 'models/dish_037.glb', 'usdz' => 'models/dish_037.usdz'],
        'Cheese Ejjeh' => ['glb' => 'models/dish_012.glb', 'usdz' => 'models/dish_012.usdz'],
        'Chekaf Lahmeh' => ['glb' => 'models/dish_088.glb', 'usdz' => 'models/dish_088.usdz'],
        'Chicken Kabab' => ['glb' => 'models/dish_091.glb', 'usdz' => 'models/dish_091.usdz'],
        'Chicken Shawarma Platter' => ['glb' => 'models/dish_102.glb', 'usdz' => 'models/dish_102.usdz'],
        'Chicken Shawarma Sandwich' => ['glb' => 'models/dish_108.glb', 'usdz' => 'models/dish_108.usdz'],
        'Chicken Tawook with Herbs' => ['glb' => 'models/dish_095.glb', 'usdz' => 'models/dish_095.usdz'],
        'Crispy Cauliflower with green aioli' => ['glb' => 'models/dish_067.glb', 'usdz' => 'models/dish_067.usdz'],
        'Eggs with Awarma' => ['glb' => 'models/dish_002.glb', 'usdz' => 'models/dish_002.usdz'],
        'Eggs with Makanek' => ['glb' => 'models/dish_004.glb', 'usdz' => 'models/dish_004.usdz'],
        'Eggs with Sujuk' => ['glb' => 'models/dish_003.glb', 'usdz' => 'models/dish_003.usdz'],
        'Eggs with Zaatar' => ['glb' => 'models/dish_001.glb', 'usdz' => 'models/dish_001.usdz'],
        'Elia`s Tabliyeh For Four' => ['glb' => 'models/dish_164.glb', 'usdz' => 'models/dish_164.usdz'],
        'Elia`s Tabliyeh For Two' => ['glb' => 'models/dish_163.glb', 'usdz' => 'models/dish_163.usdz'],
        'Fakharet Chicken Ras Asfour with Basil' => ['glb' => 'models/dish_107.glb', 'usdz' => 'models/dish_107.usdz'],
        'Fakharet Lahmet Ras Asfour' => ['glb' => 'models/dish_106.glb', 'usdz' => 'models/dish_106.usdz'],
        'Fakharet Sautéed Chicken Liver with debs' => ['glb' => 'models/dish_105.glb', 'usdz' => 'models/dish_105.usdz'],
        'Falafel' => ['glb' => 'models/dish_021.glb', 'usdz' => 'models/dish_021.usdz'],
        'Fattoush' => ['glb' => 'models/dish_046.glb', 'usdz' => 'models/dish_046.usdz'],
        'Fish Shawarma Platter' => ['glb' => 'models/dish_103.glb', 'usdz' => 'models/dish_103.usdz'],
        'Fish Shawarma Sandwich' => ['glb' => 'models/dish_103.glb', 'usdz' => 'models/dish_103.usdz'],
        'Fish Tawook' => ['glb' => 'models/dish_096.glb', 'usdz' => 'models/dish_096.usdz'],
        'Foul with Orange & Tahini' => ['glb' => 'models/dish_023.glb', 'usdz' => 'models/dish_023.usdz'],
        'Freekeh & Kale Salad  and chicken' => ['glb' => 'models/dish_049.glb', 'usdz' => 'models/dish_049.usdz'],
        'French Fries' => ['glb' => 'models/dish_026.glb', 'usdz' => 'models/dish_026.usdz'],
        'Fried Kibbeh' => ['glb' => 'models/dish_070.glb', 'usdz' => 'models/dish_070.usdz'],
        'Fruit Platter' => ['glb' => 'models/dish_160.glb', 'usdz' => 'models/dish_160.usdz'],
        'Grilled Chicken' => ['glb' => 'models/dish_097.glb', 'usdz' => 'models/dish_097.usdz'],
        'Grilled Halloumi' => ['glb' => 'models/dish_013.glb', 'usdz' => 'models/dish_013.usdz'],
        'Halawet El Jeben' => ['glb' => 'models/dish_154.glb', 'usdz' => 'models/dish_154.usdz'],
        'Halloumi  Knafeh' => ['glb' => 'models/dish_014.glb', 'usdz' => 'models/dish_014.usdz'],
        'Halloumi Knafeh' => ['glb' => 'models/dish_014.glb', 'usdz' => 'models/dish_014.usdz'],
        'Harra Batata' => ['glb' => 'models/dish_027.glb', 'usdz' => 'models/dish_027.usdz'],
        'Harra Samkeh' => ['glb' => 'models/dish_104.glb', 'usdz' => 'models/dish_104.usdz'],
        'Hummus' => ['glb' => 'models/dish_016.glb', 'usdz' => 'models/dish_016.usdz'],
        'Hummus Beiruti' => ['glb' => 'models/dish_017.glb', 'usdz' => 'models/dish_017.usdz'],
        'Hummus with Beef Shawarma' => ['glb' => 'models/dish_018.glb', 'usdz' => 'models/dish_018.usdz'],
        'Hummus with Pesto, Tomato & Halloumi' => ['glb' => 'models/dish_019.glb', 'usdz' => 'models/dish_019.usdz'],
        'ICE Cream Knafeh' => ['glb' => 'models/dish_159.glb', 'usdz' => 'models/dish_159.usdz'],
        'Jabaliyeh Saladwith kibbeh karaz' => ['glb' => 'models/dish_050.glb', 'usdz' => 'models/dish_050.usdz'],
        'Jebneh Rakakat' => ['glb' => 'models/dish_015.glb', 'usdz' => 'models/dish_015.usdz'],
        'Kabab' => ['glb' => 'models/dish_089.glb', 'usdz' => 'models/dish_089.usdz'],
        'Kabab Sandwich' => ['glb' => 'models/dish_111.glb', 'usdz' => 'models/dish_111.usdz'],
        'Kabab with Eggplant' => ['glb' => 'models/dish_090.glb', 'usdz' => 'models/dish_090.usdz'],
        'Kafta Kabab with Tahini' => ['glb' => 'models/dish_092.glb', 'usdz' => 'models/dish_092.usdz'],
        'Kafta Kabab with Tomato Sauce' => ['glb' => 'models/dish_093.glb', 'usdz' => 'models/dish_093.usdz'],
        'Kafta with Cheese' => ['glb' => 'models/dish_039.glb', 'usdz' => 'models/dish_039.usdz'],
        'Kibbeh Malsa' => ['glb' => 'models/dish_086.glb', 'usdz' => 'models/dish_086.usdz'],
        'Kibbeh with Mint Labneh' => ['glb' => 'models/dish_071.glb', 'usdz' => 'models/dish_071.usdz'],
        'Kibbet sajjyeh' => ['glb' => 'models/dish_072.glb', 'usdz' => 'models/dish_072.usdz'],
        'Knafeh Kheshneh' => ['glb' => 'models/dish_156.glb', 'usdz' => 'models/dish_156.usdz'],
        'Knafeh Naameh' => ['glb' => 'models/dish_155.glb', 'usdz' => 'models/dish_155.usdz'],
        'Labneh (plain)' => ['glb' => 'models/dish_009.glb', 'usdz' => 'models/dish_009.usdz'],
        'Labneh with Makdous' => ['glb' => 'models/dish_010.glb', 'usdz' => 'models/dish_010.usdz'],
        'Lahm Bi Ajin' => ['glb' => 'models/dish_040.glb', 'usdz' => 'models/dish_040.usdz'],
        'Lamb Cutlets with Rosemary' => ['glb' => 'models/dish_099.glb', 'usdz' => 'models/dish_099.usdz'],
        'Lentil Soup' => ['glb' => 'models/dish_043.glb', 'usdz' => 'models/dish_043.usdz'],
        'Mafarket Batata' => ['glb' => 'models/dish_008.glb', 'usdz' => 'models/dish_008.usdz'],
        'Mdammas Foul' => ['glb' => 'models/dish_022.glb', 'usdz' => 'models/dish_022.usdz'],
        'Meat Sambousek' => ['glb' => 'models/dish_074.glb', 'usdz' => 'models/dish_074.usdz'],
        'Meatballs with Tomato & Tahini' => ['glb' => 'models/dish_078.glb', 'usdz' => 'models/dish_078.usdz'],
        'Mhammara' => ['glb' => 'models/dish_031.glb', 'usdz' => 'models/dish_031.usdz'],
        'Mix Platter Shots' => ['glb' => 'models/dish_161.glb', 'usdz' => 'models/dish_161.usdz'],
        'Mix Veggies' => ['glb' => 'models/dish_032.glb', 'usdz' => 'models/dish_032.usdz'],
        'Mixed Grill' => ['glb' => 'models/dish_087.glb', 'usdz' => 'models/dish_087.usdz'],
        'Msabbaha Hummus' => ['glb' => 'models/dish_020.glb', 'usdz' => 'models/dish_020.usdz'],
        'Musakhan Rolls' => ['glb' => 'models/dish_075.glb', 'usdz' => 'models/dish_075.usdz'],
        'Mutabbal' => ['glb' => 'models/dish_029.glb', 'usdz' => 'models/dish_029.usdz'],
        'Mutabbal Beetroot' => ['glb' => 'models/dish_030.glb', 'usdz' => 'models/dish_030.usdz'],
        'Nayyeh Kibbeh' => ['glb' => 'models/dish_084.glb', 'usdz' => 'models/dish_084.usdz'],
        'Orfaliyeh' => ['glb' => 'models/dish_085.glb', 'usdz' => 'models/dish_085.usdz'],
        'Osmaliyeh Shrimp & Tartar Sauce' => ['glb' => 'models/dish_080.glb', 'usdz' => 'models/dish_080.usdz'],
        'Othmallyeh' => ['glb' => 'models/dish_157.glb', 'usdz' => 'models/dish_157.usdz'],
        'Provençal  Calamari w/ tomato' => ['glb' => 'models/dish_082.glb', 'usdz' => 'models/dish_082.usdz'],
        'Provençal Grilled Chicken Wings' => ['glb' => 'models/dish_083.glb', 'usdz' => 'models/dish_083.usdz'],
        'Provençal Shrimp' => ['glb' => 'models/dish_081.glb', 'usdz' => 'models/dish_081.usdz'],
        'Rocca Salad' => ['glb' => 'models/dish_047.glb', 'usdz' => 'models/dish_047.usdz'],
        'Scrambled Eggs' => ['glb' => 'models/dish_005.glb', 'usdz' => 'models/dish_005.usdz'],
        'Shakshouka' => ['glb' => 'models/dish_007.glb', 'usdz' => 'models/dish_007.usdz'],
        'Shanklish with Tomato' => ['glb' => 'models/dish_011.glb', 'usdz' => 'models/dish_011.usdz'],
        'Shish Tawook' => ['glb' => 'models/dish_094.glb', 'usdz' => 'models/dish_094.usdz'],
        'Shrimp Fatteh' => ['glb' => 'models/dish_079.glb', 'usdz' => 'models/dish_079.usdz'],
        'Sujuk with Tomato' => ['glb' => 'models/dish_028.glb', 'usdz' => 'models/dish_028.usdz'],
        'Sunny-Side-Up Eggs' => ['glb' => 'models/dish_006.glb', 'usdz' => 'models/dish_006.usdz'],
        'Tabbouleh' => ['glb' => 'models/dish_045.glb', 'usdz' => 'models/dish_045.usdz'],
        'Tabboulet Maftoul – Beit Elia' => ['glb' => 'models/dish_048.glb', 'usdz' => 'models/dish_048.usdz'],
        'Tawook Sandwich' => ['glb' => 'models/dish_110.glb', 'usdz' => 'models/dish_110.usdz'],
        'Tomato & Keshek' => ['glb' => 'models/dish_038.glb', 'usdz' => 'models/dish_038.usdz'],
        'Um Ali' => ['glb' => 'models/dish_153.glb', 'usdz' => 'models/dish_153.usdz'],
        'Vermicelli & Chicken Soup' => ['glb' => 'models/dish_042.glb', 'usdz' => 'models/dish_042.usdz'],
        'Warak Enab' => ['glb' => 'models/dish_060.glb', 'usdz' => 'models/dish_060.usdz'],
        'Wild Zaatar with bulghary cheese' => ['glb' => 'models/dish_034.glb', 'usdz' => 'models/dish_034.usdz'],
        'Zaatar' => ['glb' => 'models/dish_033.glb', 'usdz' => 'models/dish_033.usdz'],
        'Zaatar & Cheese' => ['glb' => 'models/dish_035.glb', 'usdz' => 'models/dish_035.usdz'],
        'Zaatar with Labneh' => ['glb' => 'models/dish_036.glb', 'usdz' => 'models/dish_036.usdz'],
    ];

    public function handle(): int
    {
        $filePath = $this->argument('file') ?: base_path('menue.xlsx');

        if (! file_exists($filePath)) {
            $this->error("Menu file not found at: {$filePath}");

            return self::FAILURE;
        }

        $this->info("Starting menu migration from: {$filePath}");

        // 1. Remove all existing products, product_tag relations, and categories
        $this->warn('Removing existing products, product_tag relations, and categories from database...');
        Schema::disableForeignKeyConstraints();
        DB::table('product_tag')->truncate();
        DB::table('products')->truncate();
        DB::table('categories')->truncate();
        Schema::enableForeignKeyConstraints();
        $this->info('Database tables truncated successfully.');

        // 2. Load Excel spreadsheet
        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray();

        // 3. Create Root Category: Elia's Chef
        $foodCategory = Category::create([
            'parent_id' => null,
            'name_en' => "Elia's Chef",
            'name_ar' => 'شيف إيليا',
            'description_en' => 'Breakfast, mezze, fire and the kitchen.',
            'description_ar' => 'فطور، مقبلات، مشاوي، ومطبخ البيت.',
            'icon' => '<svg viewBox="0 0 28 28" fill="none" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"><path d="M9 4v9m0 0v11m-4-20v6a4 4 0 0 0 8 0V4" /><path d="M23 4c-2.4 1.6-3.4 4-3.4 7 0 2 .6 3.2 2 3.8V24" /></svg>',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        // 4. Create subcategories under Elia's Chef
        $subcategoriesConfig = [
            'breakfast_platters' => [
                'name_en' => 'Breakfast Platters',
                'name_ar' => 'صواني الفطور',
                'description_en' => 'Traditional Lebanese breakfast platters and spreads.',
                'description_ar' => 'صواني فطور وأطباق تقليدية.',
                'sort_order' => 1,
            ],
            'from_the_oven' => [
                'name_en' => 'From the Oven',
                'name_ar' => 'من الفرن',
                'description_en' => 'Fresh pastries and manakeesh straight from the house oven.',
                'description_ar' => 'مناقيش ومعجنات طازجة مباشرة من فرن البيت.',
                'sort_order' => 2,
            ],
            'soups' => [
                'name_en' => 'Soups',
                'name_ar' => 'شوربات ساخنة',
                'description_en' => 'Warm, comforting seasonal soups.',
                'description_ar' => 'شوربات ساخنة محضّرة يومياً.',
                'sort_order' => 3,
            ],
            'salads' => [
                'name_en' => 'Salads',
                'name_ar' => 'سلطات فريش',
                'description_en' => 'Crisp greens and authentic Mediterranean salads.',
                'description_ar' => 'سلطات طازجة بمكونات بلدية.',
                'sort_order' => 4,
            ],
            'cold_mezze' => [
                'name_en' => 'Cold Mezze',
                'name_ar' => 'المازة الباردة',
                'description_en' => 'Classic Lebanese cold mezze made from scratch.',
                'description_ar' => 'أصناف مازة باردة أصيلة.',
                'sort_order' => 5,
            ],
            'hot_mezze' => [
                'name_en' => 'Hot Mezze',
                'name_ar' => 'المازة الساخنة',
                'description_en' => 'Savory hot mezze served crisp and sizzling.',
                'description_ar' => 'مقبلات ومازة ساخنة شهية.',
                'sort_order' => 6,
            ],
            'nayyeh' => [
                'name_en' => 'Nayyeh',
                'name_ar' => 'النية',
                'description_en' => 'Fresh raw meat specialties prepared to order.',
                'description_ar' => 'أطباق لحم نيّ طازجة تُحضّر عند الطلب.',
                'sort_order' => 7,
            ],
            'on_the_charcoal' => [
                'name_en' => 'On the Charcoal',
                'name_ar' => 'مشاوي',
                'description_en' => 'Prime cuts and skewers grilled over hot wood embers.',
                'description_ar' => 'مشاوي متبّلة ومشوية على الفحم والحطب.',
                'sort_order' => 8,
            ],
            'main_courses' => [
                'name_en' => 'Main Courses',
                'name_ar' => 'الأطباق الرئيسية',
                'description_en' => 'Generous platters and specialty Lebanese main dishes.',
                'description_ar' => 'أطباق رئيسية غنية ومميزة.',
                'sort_order' => 9,
            ],
            'fakharet' => [
                'name_en' => 'Fakharet',
                'name_ar' => 'فخارات',
                'description_en' => 'Rich clay-pot dishes cooked slowly in the oven.',
                'description_ar' => 'فخارات ساخنة مطبوخة على مهل في الفرن.',
                'sort_order' => 10,
            ],
            'sandwiches' => [
                'name_en' => 'Sandwiches',
                'name_ar' => 'سندويشات إيليا',
                'description_en' => 'Rolled sandwiches with signature pickles and garlic sauces.',
                'description_ar' => 'سندويشات بخبز التورتيلا والصاج مع المخللات والمثومة.',
                'sort_order' => 11,
            ],
        ];

        $categoriesMap = [];
        foreach ($subcategoriesConfig as $key => $config) {
            $categoriesMap[$key] = Category::create([
                'parent_id' => $foodCategory->id,
                'name_en' => $config['name_en'],
                'name_ar' => $config['name_ar'],
                'description_en' => $config['description_en'],
                'description_ar' => $config['description_ar'],
                'sort_order' => $config['sort_order'],
                'is_active' => true,
            ]);
        }

        // Fetch tags for attaching
        $rawTag = Tag::where('slug', 'raw')->first();
        $signatureTag = Tag::where('slug', 'signature')->first();

        // 5. Parse items and insert products
        $currentHeader = '';
        $productsCreated = 0;
        $categorySortCounters = [];

        foreach ($rows as $i => $row) {
            if ($i === 0) {
                continue;
            }

            $c0 = trim((string) ($row[0] ?? ''));
            $c1 = trim((string) ($row[1] ?? ''));
            $c2 = trim((string) ($row[2] ?? ''));
            $c3 = trim((string) ($row[3] ?? ''));
            $c4 = trim((string) ($row[4] ?? ''));
            $c5 = trim((string) ($row[5] ?? ''));

            // Section header row
            if ($c0 !== '' && $c1 === '' && $c2 === '' && $c3 === '' && $c4 === '' && $c5 === '') {
                $currentHeader = $c0;

                continue;
            }

            // Skip empty rows
            if ($c1 === '' && $c2 === '') {
                continue;
            }

            // Determine target category
            $categoryKey = null;
            if ($c3 === 'BREAKFAST PLATTERS') {
                $categoryKey = 'breakfast_platters';
            } elseif ($c3 === 'FROM THE OVEN' || str_contains($currentHeader, 'Oven')) {
                $categoryKey = 'from_the_oven';
            } elseif ($c3 === 'SOUPS' || str_contains($currentHeader, 'SOUPS')) {
                $categoryKey = 'soups';
            } elseif ($c3 === 'SALADS' || str_contains($currentHeader, 'SALADS')) {
                $categoryKey = 'salads';
            } elseif (trim($c3) === 'COLD MEZZE' || str_contains($currentHeader, 'COLD MEZZE')) {
                $categoryKey = 'cold_mezze';
            } elseif (trim($c3) === 'HOT MEZZE' || str_contains($currentHeader, 'HOT MEZZE')) {
                $categoryKey = 'hot_mezze';
            } elseif (trim($c3) === 'NAYYEH' || str_contains($currentHeader, 'NAYYEH')) {
                $categoryKey = 'nayyeh';
            } elseif (trim($c3) === 'ON THE CHARCOAL' || str_contains($currentHeader, 'CHARCOAL')) {
                $categoryKey = 'on_the_charcoal';
            } elseif (trim($c3) === 'MAIN COURSES') {
                $categoryKey = 'main_courses';
            } elseif (str_contains($currentHeader, 'فخارات')) {
                $categoryKey = 'fakharet';
            } elseif (trim($c3) === 'SANDWICHES' || str_contains($currentHeader, 'SANDWICHES')) {
                $categoryKey = 'sandwiches';
            }

            if (! $categoryKey || ! isset($categoriesMap[$categoryKey])) {
                $this->warn("Row {$i} could not be mapped to a category: {$c1} / {$c2}");

                continue;
            }

            $category = $categoriesMap[$categoryKey];

            // Normalize names
            $nameEn = $c1;
            $nameAr = $c2;

            $isFeatured = false;
            if (str_contains($nameEn, '★') || str_contains($nameAr, '★')) {
                $isFeatured = true;
                $nameEn = trim(str_replace('★', '', $nameEn));
                $nameAr = trim(str_replace('★', '', $nameAr));
            }

            // Normalize capitalization for fakharet items
            if (str_starts_with($nameEn, 'fakharet ')) {
                $nameEn = 'Fakharet '.ucfirst(substr($nameEn, 9));
            }

            // Parse price
            $price = 0.00;
            if ($c4 !== '' && is_numeric($c4)) {
                $price = (float) $c4;
            }

            // Filter out placeholder notes from description_ar
            $descAr = $c5 !== '' ? $c5 : null;
            if ($descAr !== null) {
                if (str_contains($descAr, 'بطاقة الوصفة') || str_contains($descAr, 'لا توجد بطاقة')) {
                    $descAr = null;
                }
            }

            // Calculate sort order
            $categorySortCounters[$categoryKey] = ($categorySortCounters[$categoryKey] ?? 0) + 1;
            $sortOrder = $categorySortCounters[$categoryKey];

            $modelGlb = null;
            $modelUsdz = null;
            $arEnabled = false;

            if (isset(self::$arModelsMap[$nameEn])) {
                $modelGlb = self::$arModelsMap[$nameEn]['glb'];
                $modelUsdz = self::$arModelsMap[$nameEn]['usdz'];
                $arEnabled = true;
            }

            $product = Product::create([
                'category_id' => $category->id,
                'name_en' => $nameEn,
                'name_ar' => $nameAr,
                'description_en' => null,
                'description_ar' => $descAr,
                'price' => $price,
                'calories' => null,
                'image' => null,
                'model_glb' => $modelGlb,
                'model_usdz' => $modelUsdz,
                'ar_enabled' => $arEnabled,
                'is_active' => true,
                'is_featured' => $isFeatured,
                'sort_order' => $sortOrder,
            ]);

            // Attach tags
            $tagsToAttach = [];
            if ($categoryKey === 'nayyeh' && $rawTag) {
                $tagsToAttach[] = $rawTag->id;
            }
            if ($isFeatured && $signatureTag) {
                $tagsToAttach[] = $signatureTag->id;
            }
            if (! empty($tagsToAttach)) {
                $product->tags()->sync($tagsToAttach);
            }

            $productsCreated++;
        }

        // 6. Clear menu cache
        MenuCacheService::clear();

        $this->info("Successfully created {$productsCreated} products across ".count($categoriesMap)." subcategories under Elia's Chef.");
        $this->info('Menu cache cleared.');

        return self::SUCCESS;
    }
}
