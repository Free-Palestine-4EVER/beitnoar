<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use App\Services\MenuCacheService;
use App\Services\MenuStaticBuilder;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        // Ensure static files rebuild after tags are synced via the relationship Select
        MenuCacheService::clear();
        MenuStaticBuilder::shouldRebuild();
    }
}
