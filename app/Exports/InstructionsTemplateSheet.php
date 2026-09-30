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

class InstructionsTemplateSheet implements Export, FromArray, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
{
    public function title(): string
    {
        return 'Instructions';
    }

    public function headings(): array
    {
        return [
            'Column',
            'Required',
            'Description',
            'Example',
        ];
    }

    public function array(): array
    {
        return [
            [
                'Column' => 'category',
                'Required' => 'Yes',
                'Description' => 'Existing category English name (trimmed, case-insensitive).',
                'Example' => 'Breakfast Platters',
            ],
            [
                'Column' => 'name_en',
                'Required' => 'Yes',
                'Description' => 'English product name (unique within the same category).',
                'Example' => 'Foul Moudammas',
            ],
            [
                'Column' => 'name_ar',
                'Required' => 'Yes',
                'Description' => 'Arabic product name.',
                'Example' => 'فول مدمس',
            ],
            [
                'Column' => 'description_en',
                'Required' => 'No',
                'Description' => 'English description of the dish.',
                'Example' => 'Slow-cooked fava beans with lemon, garlic and olive oil.',
            ],
            [
                'Column' => 'description_ar',
                'Required' => 'No',
                'Description' => 'Arabic description of the dish.',
                'Example' => 'فول مطبوخ على مهل مع ليمون وثوم وزيت زيتون.',
            ],
            [
                'Column' => 'price',
                'Required' => 'Yes',
                'Description' => 'Numeric price in JD (e.g. 4.5). Do not write "JD".',
                'Example' => '4.5',
            ],
            [
                'Column' => 'calories',
                'Required' => 'No',
                'Description' => 'Integer number of calories (kcal).',
                'Example' => '320',
            ],
            [
                'Column' => 'tags',
                'Required' => 'No',
                'Description' => 'Comma-separated tag slugs. Available slugs: vegan, vegetarian, gluten-free, signature, to-share, caffeine-free, raw.',
                'Example' => 'vegan,gluten-free',
            ],
            [
                'Column' => 'is_active',
                'Required' => 'No',
                'Description' => 'Product visibility status: 1, 0, yes, no, true, false. Defaults to 1 (active).',
                'Example' => '1',
            ],
            [
                'Column' => 'is_featured',
                'Required' => 'No',
                'Description' => 'Featured flag on menu: 1, 0, yes, no, true, false. Defaults to 0.',
                'Example' => '0',
            ],
            [
                'Column' => 'sort_order',
                'Required' => 'No',
                'Description' => 'Integer sorting order. If empty, automatically assigned to next available number in category.',
                'Example' => '1',
            ],
            [
                'Column' => 'image_url',
                'Required' => 'No',
                'Description' => 'Optional public HTTP/HTTPS URL of image (jpg, jpeg, png, webp).',
                'Example' => 'https://example.com/photo.jpg',
            ],
        ];
    }

    public function styles(Worksheet $sheet): ?array
    {
        $sheet->freezePane('A2');

        return [
            1 => [
                'font' => [
                    'bold' => true,
                    'color' => ['argb' => 'FFFFFFFF'],
                    'size' => 11,
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FFA67C33'], // Beit Elia Gold
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ],
            'A1:D13' => [
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
