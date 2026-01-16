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
        $request->validate([
            'name' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'description' => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ]);

        Period::create([
            'name' => $request->name,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'description' => $request->description,
            'is_active' => $request->has('is_active'),
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
        $request->validate([
            'name' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'description' => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ]);

        $period->update([
            'name' => $request->name,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'description' => $request->description,
            'is_active' => $request->has('is_active'),
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
