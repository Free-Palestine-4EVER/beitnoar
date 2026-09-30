<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\Product;
use App\Models\Tag;
use App\Observers\MenuCacheObserver;
use App\Services\MenuService;
use App\Services\MenuStaticBuilder;
use Illuminate\Foundation\Console\ServeCommand;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(MenuService::class);
        $this->app->singleton(MenuStaticBuilder::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Category::observe(MenuCacheObserver::class);
        Product::observe(MenuCacheObserver::class);
        Tag::observe(MenuCacheObserver::class);

        // Rebuild static menu files once at the end of the request
        // This ensures all model events and pivot syncs are complete
        $this->app->terminating(function (): void {
            app(MenuStaticBuilder::class)->rebuildIfNeeded();
        });

        if (class_exists(ServeCommand::class)) {
            ServeCommand::$passthroughVariables[] = 'PHP_INI_SCAN_DIR';
            $customIniDir = base_path('.php-ini');
            if (is_dir($customIniDir)) {
                putenv("PHP_INI_SCAN_DIR=:{$customIniDir}");
                $_ENV['PHP_INI_SCAN_DIR'] = ":{$customIniDir}";
                $_SERVER['PHP_INI_SCAN_DIR'] = ":{$customIniDir}";
            }
        }
    }
}
