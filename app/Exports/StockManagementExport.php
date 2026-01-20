<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

class StockManagementExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize, WithTitle
{
    protected $inventory;
    protected $selectedAgent;

    public function __construct(Collection $inventory, string $selectedAgent)
    {
        $this->inventory = $inventory;
        $this->selectedAgent = $selectedAgent;
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        return $this->inventory;
    }

    /**
     * @return array
     */
    public function headings(): array
    {
        return [
            'Item Code',
            'Description',
            'Group',
            'Current Stock',
            'Stock In + Return',
            'Stock Out',
            'Unit',
            'Price',
            'Stock In',
            'Return Good',
            'Return Bad',
        ];
    }

    /**
     * @param mixed $row
     * @return array
     */
    public function map($row): array
    {
        return [
            $row['ITEMNO'],
            $row['DESP'],
            $row['GROUP'],
            number_format($row['current_stock'], 2),
            number_format($row['stockIn'], 2),
            number_format($row['stockOut'], 2),
            $row['UNIT'],
            number_format($row['PRICE'], 2),
            number_format($row['stockIn'] - $row['returnGood'], 2), // Stock In (without return good)
            number_format($row['returnGood'], 2),
            number_format($row['returnBad'], 2),
        ];
    }

    /**
     * @return string
     */
    public function title(): string
    {
        return 'Stock Management - Agent ' . $this->selectedAgent;
    }

    /**
     * @param Worksheet $sheet
     * @return array
     */
    public function styles(Worksheet $sheet)
    {
        // Style the header row
        $sheet->getStyle('A1:K1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '007BFF'],
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '000000'],
                ],
            ],
        ]);

        // Style data rows
        $lastRow = $this->inventory->count() + 1;
        if ($lastRow > 1) {
            $sheet->getStyle('A2:K' . $lastRow)->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => 'DDDDDD'],
                    ],
                ],
            ]);

            // Style numeric columns (D, E, F, H, I, J, K)
            $sheet->getStyle('D2:F' . $lastRow)->getAlignment()->setHorizontal('right');
            $sheet->getStyle('H2:K' . $lastRow)->getAlignment()->setHorizontal('right');
        }

        // Auto-size columns
        foreach (range('A', 'K') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        return [];
    }
}