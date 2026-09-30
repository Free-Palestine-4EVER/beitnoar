<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class MenuCacheService
{
    public const CACHE_KEY = 'restaurant-menu';
    public const CACHE_TTL = 3600;

    public static function get(): array
    {
        return Cache::store('file')->remember(
            self::CACHE_KEY,
            self::CACHE_TTL,
            fn () => app(MenuService::class)->getFullMenu()
        );
    }

    public static function clear(): void
    {
        Cache::store('file')->forget(self::CACHE_KEY);
    }
}
