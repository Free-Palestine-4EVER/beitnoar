<?php

namespace App\Observers;

use App\Services\MenuCacheService;
use App\Services\MenuStaticBuilder;

class MenuCacheObserver
{
    public function created($model): void
    {
        MenuCacheService::clear();
        MenuStaticBuilder::shouldRebuild();
    }

    public function updated($model): void
    {
        MenuCacheService::clear();
        MenuStaticBuilder::shouldRebuild();
    }

    public function deleted($model): void
    {
        MenuCacheService::clear();
        MenuStaticBuilder::shouldRebuild();
    }
}
