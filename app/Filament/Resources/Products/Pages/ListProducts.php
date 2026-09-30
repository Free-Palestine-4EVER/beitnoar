<?php

namespace App\Filament\Resources\Products\Pages;

use App\Exports\ProductImportTemplateExport;
use App\Filament\Resources\Products\ProductResource;
use App\Services\ProductImportService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Maatwebsite\Excel\Facades\Excel;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),

            Action::make('downloadTemplate')
                ->label('Download Excel Template')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->action(function () {
                    return Excel::download(
                        new ProductImportTemplateExport(),
                        'products_import_template.xlsx'
                    );
                }),

            Action::make('importProducts')
                ->label('Import Products')
                ->icon(Heroicon::OutlinedArrowUpTray)
                ->color('primary')
                ->modalHeading('Import Products from Excel')
                ->modalDescription('Upload an .xlsx file using the template structure. Empty rows will be ignored. If any row contains an error, no products will be imported.')
                ->modalSubmitActionLabel('Import')
                ->form([
                    FileUpload::make('excel_file')
                        ->label('Excel File (.xlsx)')
                        ->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'application/vnd.ms-excel',
                        ])
                        ->maxSize(5120) // 5MB
                        ->disk('local')
                        ->directory('imports')
                        ->required(),
                ])
                ->action(function (array $data, ProductImportService $importService): void {
                    $relativeFilePath = $data['excel_file'];
                    $absolutePath = Storage::disk('local')->path($relativeFilePath);

                    if (!file_exists($absolutePath)) {
                        Notification::make()
                            ->title('File not found')
                            ->body('The uploaded file could not be read. Please try again.')
                            ->danger()
                            ->send();
                        return;
                    }

                    $result = $importService->import($absolutePath);

                    // Clean up uploaded temporary import file
                    Storage::disk('local')->delete($relativeFilePath);

                    if ($result['success']) {
                        Notification::make()
                            ->title('Products imported successfully')
                            ->body("Imported: {$result['imported_count']} products.")
                            ->success()
                            ->send();
                    } else {
                        $errorList = collect($result['errors'])->take(5)->map(function ($err) {
                            return "<li><strong>Row {$err['row']} ({$err['product']}):</strong> {$err['error']}</li>";
                        })->implode('');

                        $remaining = count($result['errors']) - 5;
                        if ($remaining > 0) {
                            $errorList .= "<li><em>...and {$remaining} more error(s).</em></li>";
                        }

                        $bodyHtml = "<div><p class='mb-2'>No products were imported due to validation errors:</p><ul class='list-disc pl-4 text-xs space-y-1'>{$errorList}</ul>";

                        if (!empty($result['error_file_url'])) {
                            $bodyHtml .= "<p class='mt-2'><a href='{$result['error_file_url']}' target='_blank' class='font-bold underline text-danger-600'>Download full error report (Excel)</a></p>";
                        }
                        $bodyHtml .= "</div>";

                        Notification::make()
                            ->title('Import Failed')
                            ->body(new HtmlString($bodyHtml))
                            ->danger()
                            ->persistent()
                            ->send();
                    }
                }),
        ];
    }
}
