<?php

namespace App\Filament\Resources\Products\Tables;

use App\Services\MenuCacheService;
use App\Services\MenuStaticBuilder;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')
            ->afterReordering(function (): void {
                MenuCacheService::clear();
                MenuStaticBuilder::shouldRebuild();
            })
            ->defaultSort('sort_order', 'asc')
            ->columns([
                TextColumn::make('id')
                    ->sortable(),
                ImageColumn::make('image')
                    ->disk('public'),
                TextColumn::make('name_en')
                    ->label('English Name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name_ar')
                    ->label('Arabic Name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('category.name_en')
                    ->label('Category')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('price')
                    ->label('Price')
                    ->money('JOD')
                    ->sortable(),
                TextColumn::make('calories')
                    ->label('Calories')
                    ->numeric()
                    ->sortable()
                    ->placeholder('-'),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),
                IconColumn::make('is_featured')
                    ->label('Featured')
                    ->boolean()
                    ->sortable(),
                IconColumn::make('ar_enabled')
                    ->label('AR')
                    ->boolean()
                    ->sortable(),
                IconColumn::make('video')
                    ->label('Video')
                    ->boolean(fn ($record) => ! empty($record->video))
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label('Updated At')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('category_id')
                    ->label('Category')
                    ->relationship('category', 'name_en'),
                TernaryFilter::make('is_active')
                    ->label('Active Status'),
                TernaryFilter::make('is_featured')
                    ->label('Featured Status'),
                TernaryFilter::make('ar_enabled')
                    ->label('AR Enabled'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
