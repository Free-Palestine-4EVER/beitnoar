<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ProductsTemplateSheet implements Export, FromArray, WithHeadings, WithTitle, ShouldAutoSize, WithStyles
{
    public function title(): string
    {
        return 'Products';
    }

    public function headings(): array
    {
        return [
            'category',
            'name_en',
            'name_ar',
            'description_en',
            'description_ar',
            'price',
            'calories',
            'tags',
            'is_active',
            'is_featured',
            'sort_order',
            'image_url',
        ];
    }

    public function array(): array
    {
        return [
            [
                'category' => 'Breakfast Platters',
                'name_en' => 'Foul Moudammas',
                'name_ar' => 'فول مدمس',
                'description_en' => 'Slow-cooked fava beans with lemon, garlic and olive oil.',
                'description_ar' => 'فول مطبوخ على مهل مع ليمون وثوم وزيت زيتون.',
                'price' => 4.5,
                'calories' => 320,
                'tags' => 'vegan,gluten-free',
                'is_active' => 1,
                'is_featured' => 0,
                'sort_order' => 1,
                'image_url' => '',
            ],
            [
                'category' => 'Breakfast Platters',
                'name_en' => 'Beit Elia Breakfast Platter',
                'name_ar' => 'صينية فطور بيت إيليا',
                'description_en' => 'A selection of traditional breakfast dishes.',
                'description_ar' => 'تشكيلة من أطباق الفطور التقليدية.',
                'price' => 12.0,
                'calories' => 980,
                'tags' => 'signature,to-share',
                'is_active' => 1,
                'is_featured' => 1,
                'sort_order' => 2,
                'image_url' => 'https://example.com/product.jpg',
            ],
        ];
    }

    public function styles(Worksheet $sheet): ?array
    {
        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:L1');

        return [
            1 => [
                'font' => [
                    'bold' => true,
                    'color' => ['argb' => 'FFFFFFFF'],
                    'size' => 11,
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FF2E2D2B'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ],
            'A1:L3' => [
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['argb' => 'FFDFDBD4'],
                    ],
                ],
            ],
        ];
    }
}
