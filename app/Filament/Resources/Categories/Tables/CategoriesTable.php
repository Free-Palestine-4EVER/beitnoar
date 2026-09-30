<?php

namespace App\Filament\Resources\Categories\Tables;

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

class CategoriesTable
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
                TextColumn::make('name_en')
                    ->label('Name (EN)')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name_ar')
                    ->label('Name (AR)')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('parent.name_en')
                    ->label('Parent')
                    ->searchable()
                    ->sortable()
                    ->placeholder('None (Root)'),
                ImageColumn::make('image')
                    ->disk('public'),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label('Updated At')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Active Status'),
                SelectFilter::make('parent_id')
                    ->label('Parent Category')
                    ->relationship('parent', 'name_en'),
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
