<?php

namespace App\Services\PDF;

use TCPDF;

class StockManagementPDF extends TCPDF
{
    protected $agentName;
    protected $exportDate;

    public function setAgentName(string $agentName): void
    {
        $this->agentName = $agentName;
    }

    public function setExportDate(string $exportDate): void
    {
        $this->exportDate = $exportDate;
    }

    /**
     * Custom Header
     */
    public function Header()
    {
        // Set font
        $this->SetFont('helvetica', 'B', 14);

        // Title
        $this->Cell(0, 8, 'Stock Management Report', 0, 1, 'C');

        // Agent name
        $this->SetFont('helvetica', '', 10);
        $this->Cell(0, 5, 'Agent: ' . $this->agentName, 0, 1, 'C');

        // Export date
        $this->SetFont('helvetica', '', 8);
        $this->SetTextColor(100, 100, 100);
        $this->Cell(0, 4, 'Generated: ' . $this->exportDate, 0, 1, 'C');

        // Reset text color
        $this->SetTextColor(0, 0, 0);

        // Line separator
        $this->SetLineWidth(0.3);
        $this->SetDrawColor(0, 123, 255);
        $this->Line(10, $this->GetY() + 2, $this->getPageWidth() - 10, $this->GetY() + 2);

        // Add some space after header
        $this->Ln(5);
    }

    /**
     * Custom Footer
     */
    public function Footer()
    {
        // Position at 15 mm from bottom
        $this->SetY(-15);

        // Line separator
        $this->SetLineWidth(0.2);
        $this->SetDrawColor(200, 200, 200);
        $this->Line(10, $this->GetY(), $this->getPageWidth() - 10, $this->GetY());

        // Set font
        $this->SetFont('helvetica', '', 8);
        $this->SetTextColor(128, 128, 128);

        // Left side - Company info
        $this->Cell(0, 10, 'KBS Stock Management System', 0, 0, 'L');

        // Right side - Page number
        $this->Cell(0, 10, 'Page ' . $this->getAliasNumPage() . ' of ' . $this->getAliasNbPages(), 0, 0, 'R');
    }
}
