<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;

class GroupProductSalesByYearExport implements FromView
{
    private array $report;

    public function __construct(array $report)
    {
        $this->report = $report;
    }

    public function view(): View
    {
        return view('exports.group-product-sales-year', $this->report);
    }
}

