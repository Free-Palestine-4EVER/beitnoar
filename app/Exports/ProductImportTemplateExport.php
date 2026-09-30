<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class ProductImportTemplateExport implements Export, WithMultipleSheets
{
    use Exportable;

    public function sheets(): array
    {
        return [
            'Products' => new ProductsTemplateSheet(),
            'Instructions' => new InstructionsTemplateSheet(),
        ];
    }
}
