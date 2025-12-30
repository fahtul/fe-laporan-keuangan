<?php

namespace App\Exports;

use Illuminate\Support\Arr;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class GenericReportExport implements FromArray, WithHeadings, WithStyles, WithColumnFormatting, ShouldAutoSize, WithEvents
{
    public function __construct(
        private readonly array $unified
    ) {
    }

    public function headings(): array
    {
        return array_map(
            fn ($c) => (string) Arr::get($c, 'label', ''),
            (array) Arr::get($this->unified, 'columns', [])
        );
    }

    public function array(): array
    {
        $columns = (array) Arr::get($this->unified, 'columns', []);
        $rows = (array) Arr::get($this->unified, 'rows', []);

        $out = [];

        foreach ($rows as $row) {
            $type = (string) Arr::get($row, 'type', 'account');
            $level = (int) Arr::get($row, 'level', 0);

            $line = [];
            foreach ($columns as $col) {
                $key = (string) Arr::get($col, 'key', '');
                $align = (string) Arr::get($col, 'align', 'left');

                $val = '';

                if ($type === 'spacer') {
                    $val = '';
                } elseif ($type === 'note') {
                    $val = $key === 'label' ? (string) Arr::get($row, 'label', '') : '';
                } elseif ($type === 'section') {
                    $val = $key === 'label' ? (string) Arr::get($row, 'label', '') : '';
                } else {
                    $val = Arr::get($row, $key, '');
                }

                if ($key === 'label') {
                    $label = (string) $val;
                    $val = ($level > 0 ? str_repeat('   ', $level) : '') . $label;
                } elseif ($align === 'right') {
                    if ($type === 'section' || $type === 'note' || $type === 'spacer') {
                        $val = '';
                    } elseif ($val === null || $val === '') {
                        $val = '';
                    } else {
                        $val = (float) $val;
                    }
                } else {
                    $val = $val === null ? '' : (string) $val;
                }

                $line[] = $val;
            }

            $out[] = $line;
        }

        return $out;
    }

    public function columnFormats(): array
    {
        $columns = (array) Arr::get($this->unified, 'columns', []);
        $formats = [];

        foreach ($columns as $i => $col) {
            $align = (string) Arr::get($col, 'align', 'left');
            if ($align !== 'right') {
                continue;
            }

            $letter = $this->colLetter($i + 1);
            $formats[$letter] = NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1;
        }

        return $formats;
    }

    public function styles(Worksheet $sheet)
    {
        $columns = (array) Arr::get($this->unified, 'columns', []);
        $rows = (array) Arr::get($this->unified, 'rows', []);

        $lastCol = $this->colLetter(max(1, count($columns)));

        // Header (row 1)
        $sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
            'font' => ['bold' => true],
            'alignment' => [
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'bottom' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['argb' => 'FFD1D5DB'],
                ],
            ],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(20);

        // Per-row styles (data starts row 2)
        foreach ($rows as $idx => $row) {
            $type = (string) Arr::get($row, 'type', 'account');
            $excelRow = $idx + 2;
            $range = "A{$excelRow}:{$lastCol}{$excelRow}";

            if ($type === 'spacer') {
                $sheet->getRowDimension($excelRow)->setRowHeight(8);
                continue;
            }

            if ($type === 'section') {
                $sheet->getStyle($range)->applyFromArray([
                    'font' => ['bold' => true],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['argb' => 'FFF3F4F6'],
                    ],
                ]);
            } elseif ($type === 'subtotal') {
                $sheet->getStyle($range)->applyFromArray([
                    'font' => ['bold' => true],
                    'borders' => [
                        'top' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['argb' => 'FFD1D5DB'],
                        ],
                    ],
                ]);
            } elseif ($type === 'grand_total') {
                $sheet->getStyle($range)->applyFromArray([
                    'font' => ['bold' => true],
                    'borders' => [
                        'top' => [
                            'borderStyle' => Border::BORDER_DOUBLE,
                            'color' => ['argb' => 'FF9CA3AF'],
                        ],
                    ],
                ]);
            } elseif ($type === 'note') {
                $sheet->getStyle($range)->applyFromArray([
                    'font' => [
                        'italic' => true,
                        'color' => ['argb' => 'FF6B7280'],
                    ],
                ]);
            }
        }

        // Align numeric columns right
        foreach ($columns as $i => $col) {
            $align = (string) Arr::get($col, 'align', 'left');
            $letter = $this->colLetter($i + 1);
            $sheet->getStyle("{$letter}:{$letter}")->getAlignment()->setHorizontal(
                $align === 'right' ? Alignment::HORIZONTAL_RIGHT : Alignment::HORIZONTAL_LEFT
            );
        }

        return [];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $event->sheet->getDelegate()->freezePane('A2');
            },
        ];
    }

    private function colLetter(int $index): string
    {
        $letter = '';
        while ($index > 0) {
            $temp = ($index - 1) % 26;
            $letter = chr($temp + 65) . $letter;
            $index = (int) (($index - $temp - 1) / 26);
        }
        return $letter;
    }
}

