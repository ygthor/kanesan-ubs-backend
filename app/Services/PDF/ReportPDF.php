<?php

namespace App\Services\PDF;

use TCPDF;

class ReportPDF extends TCPDF
{
    public string $printedAt = '';

    public function Footer()
    {
        $this->SetY(-7);
        $this->SetFont('helvetica', '', 8);
        $this->Cell(0, 4, 'Printed at ' . $this->printedAt, 0, 0, 'L');
        $this->Cell(0, 4, 'Page ' . $this->getAliasNumPage() . ' of ' . $this->getAliasNbPages(), 0, 0, 'R');
    }

    public function CellSM(
        float $w,
        float $h,
        string $txt,
        $border = 0,
        int $ln = 0,
        string $align = '',
        bool $fill = false,
        string $link = '',
        int $stretch = 0,
        bool $ignore_min_height = false,
        string $calign = 'T',
        string $valign = 'M',
        bool $fitcell = false,
        int $maxChars = 10,
        float $reducedFontSize = 8.0
    ) {
        $currentSize = (float) $this->getFontSizePt();
        if ($currentSize <= 0) {
            $currentSize = 9.0;
        }

        if (mb_strlen($txt) > $maxChars) {
            $this->SetFontSize($reducedFontSize);
        }

        $result = $this->Cell(
            $w,
            $h,
            $txt,
            $border,
            $ln,
            $align,
            $fill,
            $link,
            $stretch,
            $ignore_min_height,
            $calign,
            $valign,
            $fitcell
        );

        if (mb_strlen($txt) > $maxChars) {
            $this->SetFontSize($currentSize);
        }

        return $result;
    }
}
