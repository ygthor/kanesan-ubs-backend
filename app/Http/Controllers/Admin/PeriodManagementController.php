<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Period;
use Carbon\Carbon;
use Illuminate\Http\Request;

class PeriodManagementController extends Controller
{
    /**
     * Display a listing of periods.
     */
    public function index()
    {
        $periods = Period::orderBy('start_date', 'desc')->paginate(15);
        return view('admin.periods.index', compact('periods'));
    }

    /**
     * Show the form for creating a new period.
     */
    public function create()
    {
        // Suggest start date based on latest period or current year
        $latestPeriod = Period::orderBy('start_date', 'desc')->first();

        if ($latestPeriod) {
            $suggestedStartDate = $latestPeriod->getNextPeriodStartDate()->format('Y-01-01');
            $suggestedEndDate = $latestPeriod->getNextPeriodEndDate()->format('Y-m-d');
        } else {
            $currentYear = Carbon::now()->year;
            $suggestedStartDate = Carbon::createFromDate($currentYear, 1, 1)->format('Y-01-01');
            $suggestedEndDate = Carbon::createFromDate($currentYear, 1, 1)->addMonths(17)->endOfMonth()->format('Y-m-d');
        }

        return view('admin.periods.create', compact('suggestedStartDate', 'suggestedEndDate'));
    }

    /**
     * Store a newly created period.
     */
    public function store(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        // Start Date is always fixed as 01 Jan of the selected year
        $startDate = Carbon::parse($request->start_date)->startOfYear()->format('Y-01-01');

        // End Date defaults to +18 months (June 30 of next year)
        if ($request->filled('end_date')) {
            $endDate = Carbon::parse($request->end_date)->format('Y-m-d');
        } else {
            $endDate = Carbon::parse($startDate)->addMonths(17)->endOfMonth()->format('Y-m-d');
        }

        // Check if period for this financial year already exists
        $existingSameYear = Period::where('start_date', $startDate)->first();
        if ($existingSameYear) {
            return back()
                ->withInput()
                ->withErrors(['start_date' => 'A period for FY ' . Carbon::parse($startDate)->format('Y') . ' already exists (' . $existingSameYear->start_date->format('M d, Y') . ' - ' . $existingSameYear->end_date->format('M d, Y') . ').']);
        }

        // Check for overlapping periods
        $overlappingPeriod = Period::where(function ($query) use ($startDate, $endDate) {
            $query->whereBetween('start_date', [$startDate, $endDate])
                  ->orWhereBetween('end_date', [$startDate, $endDate])
                  ->orWhere(function ($q) use ($startDate, $endDate) {
                      $q->where('start_date', '<=', $startDate)
                        ->where('end_date', '>=', $endDate);
                  });
        })->first();

        if ($overlappingPeriod) {
            return back()
                ->withInput()
                ->withErrors(['overlap' => 'The selected date range overlaps with an existing period: ' . $overlappingPeriod->financial_year . ' (' . $overlappingPeriod->start_date->format('M d, Y') . ' - ' . $overlappingPeriod->end_date->format('M d, Y') . '). If this is the previous year, please Close it first to finalize its end date to Dec 31.']);
        }

        $period = Period::create([
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);

        return redirect()->route('admin.periods.index')
            ->with('success', "Period for {$period->financial_year} (" . $period->start_date->format('M d, Y') . " - " . $period->end_date->format('M d, Y') . ") created successfully!");
    }

    /**
     * Display the specified period.
     */
    public function show(Period $period)
    {
        return view('admin.periods.show', compact('period'));
    }

    /**
     * Show the form for editing the specified period.
     */
    public function edit(Period $period)
    {
        return view('admin.periods.edit', compact('period'));
    }

    /**
     * Update the specified period.
     */
    public function update(Request $request, Period $period)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        // Start Date is always fixed as 01 Jan of the selected year
        $startDate = Carbon::parse($request->start_date)->startOfYear()->format('Y-01-01');
        $endDate = Carbon::parse($request->end_date)->format('Y-m-d');

        // Check for overlapping periods (excluding current period)
        $overlappingPeriod = Period::where('id', '!=', $period->id)
            ->where(function ($query) use ($startDate, $endDate) {
                $query->whereBetween('start_date', [$startDate, $endDate])
                      ->orWhereBetween('end_date', [$startDate, $endDate])
                      ->orWhere(function ($q) use ($startDate, $endDate) {
                          $q->where('start_date', '<=', $startDate)
                            ->where('end_date', '>=', $endDate);
                      });
            })->first();

        if ($overlappingPeriod) {
            return back()
                ->withInput()
                ->withErrors(['overlap' => 'The selected date range overlaps with an existing period: ' . $overlappingPeriod->financial_year . ' (' . $overlappingPeriod->start_date->format('M d, Y') . ' - ' . $overlappingPeriod->end_date->format('M d, Y') . ')']);
        }

        $period->update([
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);

        return redirect()->route('admin.periods.index')
            ->with('success', "Period {$period->financial_year} updated successfully!");
    }

    /**
     * Close a period (set end date to Dec 31 of start year) and auto-create the next year (+18 months).
     */
    public function close(Period $period)
    {
        $startYear = $period->start_date->year;
        $closedEndDate = $period->start_date->copy()->endOfYear()->format('Y-m-d');

        // 1. Update current period's end date to year end (Dec 31)
        $period->update([
            'end_date' => $closedEndDate,
        ]);

        // 2. Next year start date (01 Jan of next year) and default end date (+18 months: Jun 30 of subsequent year)
        $nextStartDate = $period->start_date->copy()->addYear()->startOfYear()->format('Y-01-01');
        $nextEndDate = $period->start_date->copy()->addYear()->addMonths(17)->endOfMonth()->format('Y-m-d');
        $nextYear = Carbon::parse($nextStartDate)->year;

        // Check if next year period already exists
        $existingNextPeriod = Period::where('start_date', $nextStartDate)->first();

        if (!$existingNextPeriod) {
            $createdNext = Period::create([
                'start_date' => $nextStartDate,
                'end_date' => $nextEndDate,
            ]);

            $message = "Financial Year FY {$startYear} has been closed (finalized to " . Carbon::parse($closedEndDate)->format('M d, Y') . "). Next period FY {$nextYear} (" . $createdNext->start_date->format('M d, Y') . " - " . $createdNext->end_date->format('M d, Y') . ", 18 Months) has been automatically created!";
        } else {
            $message = "Financial Year FY {$startYear} has been closed (finalized to " . Carbon::parse($closedEndDate)->format('M d, Y') . "). Next period FY {$nextYear} already exists.";
        }

        return redirect()->route('admin.periods.index')
            ->with('success', $message);
    }

    /**
     * Remove the specified period.
     */
    public function destroy(Period $period)
    {
        $fy = $period->financial_year;
        $period->delete();

        return redirect()->route('admin.periods.index')
            ->with('success', "Period {$fy} deleted successfully!");
    }
}
