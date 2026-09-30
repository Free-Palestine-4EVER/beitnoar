<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use App\Services\MenuCacheService;
use App\Services\MenuStaticBuilder;
use Filament\Resources\Pages\CreateRecord;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    protected function afterCreate(): void
    {
        // Ensure static files rebuild after tags are synced via the relationship Select
        MenuCacheService::clear();
        MenuStaticBuilder::shouldRebuild();
    }
}
