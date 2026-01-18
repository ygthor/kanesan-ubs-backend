<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Period;
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
     * Get validation rules for period creation and update.
     */
    private function getPeriodValidationRules()
    {
        return [
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ];
    }

    /**
     * Show the form for creating a new period.
     */
    public function create()
    {
        return view('admin.periods.create');
    }

    /**
     * Store a newly created period.
     */
    public function store(Request $request)
    {
        $request->validate($this->getPeriodValidationRules());

        // Check for overlapping periods
        $overlappingPeriod = Period::where(function ($query) use ($request) {
            $query->whereBetween('start_date', [$request->start_date, $request->end_date])
                  ->orWhereBetween('end_date', [$request->start_date, $request->end_date])
                  ->orWhere(function ($q) use ($request) {
                      $q->where('start_date', '<=', $request->start_date)
                        ->where('end_date', '>=', $request->end_date);
                  });
        })->first();

        if ($overlappingPeriod) {
            return back()
                ->withInput()
                ->withErrors(['overlap' => 'The selected date range overlaps with an existing period: "' . $overlappingPeriod->name . '" (' . $overlappingPeriod->start_date->format('M d, Y') . ' - ' . $overlappingPeriod->end_date->format('M d, Y') . ')']);
        }

        Period::create([
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
        ]);

        return redirect()->route('admin.periods.index')
            ->with('success', 'Period created successfully!');
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
        $request->validate($this->getPeriodValidationRules());

        // Check for overlapping periods (excluding current period)
        $overlappingPeriod = Period::where('id', '!=', $period->id)
            ->where(function ($query) use ($request) {
                $query->whereBetween('start_date', [$request->start_date, $request->end_date])
                      ->orWhereBetween('end_date', [$request->start_date, $request->end_date])
                      ->orWhere(function ($q) use ($request) {
                          $q->where('start_date', '<=', $request->start_date)
                            ->where('end_date', '>=', $request->end_date);
                      });
            })->first();

        if ($overlappingPeriod) {
            return back()
                ->withInput()
                ->withErrors(['overlap' => 'The selected date range overlaps with an existing period: "' . $overlappingPeriod->name . '" (' . $overlappingPeriod->start_date->format('M d, Y') . ' - ' . $overlappingPeriod->end_date->format('M d, Y') . ')']);
        }

        $period->update([
            
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
        ]);

        return redirect()->route('admin.periods.index')
            ->with('success', 'Period updated successfully!');
    }

    /**
     * Remove the specified period.
     */
    public function destroy(Period $period)
    {
        $period->delete();

        return redirect()->route('admin.periods.index')
            ->with('success', 'Period deleted successfully!');
    }
}
