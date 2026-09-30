<?php

namespace App\Filament\Resources\Products\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('category_id')
                    ->label('Category')
                    ->relationship('category', 'name_en')
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('name_en')
                    ->label('Product Name (English)')
                    ->required()
                    ->maxLength(255),
                TextInput::make('name_ar')
                    ->label('Product Name (Arabic)')
                    ->required()
                    ->maxLength(255),
                Textarea::make('description_en')
                    ->label('Description (English)')
                    ->columnSpanFull(),
                Textarea::make('description_ar')
                    ->label('Description (Arabic)')
                    ->columnSpanFull(),
                TextInput::make('price')
                    ->label('Price')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->prefix('JD'),
                TextInput::make('calories')
                    ->label('Calories')
                    ->numeric()
                    ->minValue(0)
                    ->suffix('kcal'),
                FileUpload::make('image')
                    ->label('Product Image')
                    ->disk('public')
                    ->directory('products')
                    ->image()
                    ->imageEditor()
                    ->imageEditorAspectRatios(['1:1'])
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/jpg'])
                    ->maxSize(2048)
                    ->helperText('Recommended image: 1200 × 1200 px (1:1), product centered with consistent spacing.'),
                FileUpload::make('video')
                    ->label('Product Video')
                    ->disk('public')
                    ->directory('products/videos')
                    ->acceptedFileTypes(['video/mp4', 'video/webm'])
                    ->maxSize(20480)
                    ->rules(['nullable', 'file', 'mimetypes:video/mp4,video/webm'])
                    ->helperText('Recommended: MP4 (H.264), square 1:1 format (720x720 or 1080x1080), 3–6s, muted, max 20MB.'),
                FileUpload::make('video_poster')
                    ->label('Video Poster Image')
                    ->disk('public')
                    ->directory('products/posters')
                    ->image()
                    ->imageEditor()
                    ->imageEditorAspectRatios(['1:1'])
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/jpg'])
                    ->maxSize(2048)
                    ->helperText('Optional 1:1 image displayed before the video loads. If omitted, Product Image will be used.'),
                FileUpload::make('model_glb')
                    ->label('3D Model (GLB)')
                    ->disk('public')
                    ->directory('products/models')
                    ->maxSize(51200)
                    ->rules(['nullable', 'file', 'extensions:glb'])
                    ->helperText('3D model in .glb format (for interactive 3D web viewer & Android AR Scene Viewer). Max 50MB.'),
                FileUpload::make('model_usdz')
                    ->label('iOS AR Model (USDZ)')
                    ->disk('public')
                    ->directory('products/models')
                    ->maxSize(51200)
                    ->rules(['nullable', 'file', 'extensions:usdz'])
                    ->helperText('Apple AR Quick Look model in .usdz format (for iPhone / iPad AR). Max 50MB.'),
                Toggle::make('ar_enabled')
                    ->label('Enable AR / 3D Preview')
                    ->helperText('Enable Augmented Reality ("View on your table") and 3D preview on the menu.')
                    ->default(false),
                Select::make('tags')
                    ->label('Tags')
                    ->relationship('tags', 'name_en')
                    ->multiple()
                    ->preload()
                    ->searchable(),
                Toggle::make('is_active')
                    ->label('Available / Active')
                    ->default(true)
                    ->required(),
                Toggle::make('is_featured')
                    ->label('Featured')
                    ->default(false)
                    ->required(),
                TextInput::make('sort_order')
                    ->label('Sort Order')
                    ->required()
                    ->numeric()
                    ->default(0),
            ]);
    }
}
