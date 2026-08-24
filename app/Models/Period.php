<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Period extends Model
{
    use HasFactory;

    protected $table = 'periods';

    protected $fillable = [
        'name',
        'start_date',
        'end_date',
        'description',
        'is_active',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
    ];

    /**
     * Determine if the period is closed (end_date <= Dec 31 of start year or <= 12 months).
     */
    public function isClosed(): bool
    {
        if (!$this->start_date || !$this->end_date) {
            return false;
        }

        $yearEnd = $this->start_date->copy()->endOfYear();
        return $this->end_date->lte($yearEnd);
    }

    /**
     * Determine if the period is open (> 12 months or > Dec 31 of start year).
     */
    public function isOpen(): bool
    {
        return !$this->isClosed();
    }

    /**
     * Get the financial year name (e.g. "FY 2025").
     */
    public function getFinancialYearAttribute(): string
    {
        return $this->start_date ? 'FY ' . $this->start_date->format('Y') : 'FY N/A';
    }

    /**
     * Get the total number of months in this period.
     */
    public function getMonthCountAttribute(): int
    {
        if (!$this->start_date || !$this->end_date) {
            return 0;
        }

        $startYear = $this->start_date->year;
        $startMonth = $this->start_date->month;
        $endYear = $this->end_date->year;
        $endMonth = $this->end_date->month;

        return ($endYear - $startYear) * 12 + ($endMonth - $startMonth) + 1;
    }

    /**
     * Get the expected closed end date for this period (Dec 31 of start year).
     */
    public function getYearEndDate(): \Carbon\Carbon
    {
        return $this->start_date->copy()->endOfYear();
    }

    /**
     * Get the start date for the next financial year (Jan 1 of next year).
     */
    public function getNextPeriodStartDate(): \Carbon\Carbon
    {
        return $this->start_date->copy()->addYear()->startOfYear();
    }

    /**
     * Get the default end date for the next financial year (Jun 30 of year after next, 18 months).
     */
    public function getNextPeriodEndDate(): \Carbon\Carbon
    {
        return $this->start_date->copy()->addYear()->addMonths(17)->endOfMonth();
    }
}
