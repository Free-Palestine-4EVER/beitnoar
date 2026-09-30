<?php

namespace App\Filament\Resources\Categories\Schemas;

use App\Models\Category;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class CategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('parent_id')
                    ->label('Parent Category')
                    ->relationship('parent', 'name_en')
                    ->searchable()
                    ->preload()
                    ->nullable()
                    ->options(function (?Category $record) {
                        $query = Category::query();
                        if ($record && $record->id) {
                            $query->where('id', '!=', $record->id);
                        }
                        return $query->pluck('name_en', 'id');
                    }),
                TextInput::make('name_en')
                    ->label('Name (English)')
                    ->required()
                    ->maxLength(255),
                TextInput::make('name_ar')
                    ->label('Name (Arabic)')
                    ->required()
                    ->maxLength(255),
                Textarea::make('description_en')
                    ->label('Description (English)')
                    ->columnSpanFull(),
                Textarea::make('description_ar')
                    ->label('Description (Arabic)')
                    ->columnSpanFull(),
                Textarea::make('icon')
                    ->label('Icon (SVG markup or path)')
                    ->columnSpanFull(),
                FileUpload::make('image')
                    ->label('Category Image')
                    ->disk('public')
                    ->directory('categories')
                    ->image()
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/jpg'])
                    ->maxSize(2048),
                TextInput::make('sort_order')
                    ->label('Sort Order')
                    ->required()
                    ->numeric()
                    ->default(0),
                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true)
                    ->required(),
            ]);
    }
}
