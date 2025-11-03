<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Territory;
use Illuminate\Http\Request;

class TerritoryManagementController extends Controller
{
    /**
     * Display a listing of territories.
     */
    public function index()
    {
        $territories = Territory::orderBy('area')->paginate(15);
        return view('admin.territories.index', compact('territories'));
    }

    /**
     * Show the form for creating a new territory.
     */
    public function create()
    {
        return view('admin.territories.create');
    }

    /**
     * Store a newly created territory.
     */
    public function store(Request $request)
    {
        $request->validate([
            'area' => 'required|string|max:255|unique:territories',
            'description' => 'required|string|max:255',
        ]);

        Territory::create([
            'area' => $request->area,
            'description' => $request->description,
        ]);

        return redirect()->route('admin.territories.index')
            ->with('success', 'Territory created successfully!');
    }

    /**
     * Display the specified territory.
     */
    public function show(Territory $territory)
    {
        return view('admin.territories.show', compact('territory'));
    }

    /**
     * Show the form for editing the specified territory.
     */
    public function edit(Territory $territory)
    {
        return view('admin.territories.edit', compact('territory'));
    }

    /**
     * Update the specified territory.
     */
    public function update(Request $request, Territory $territory)
    {
        $request->validate([
            'area' => 'required|string|max:255|unique:territories,area,' . $territory->id,
            'description' => 'required|string|max:255',
        ]);

        $territory->update([
            'area' => $request->area,
            'description' => $request->description,
        ]);

        return redirect()->route('admin.territories.index')
            ->with('success', 'Territory updated successfully!');
    }

    /**
     * Remove the specified territory.
     */
    public function destroy(Territory $territory)
    {
        // Check if territory is used by any customers
        $customerCount = $territory->customers()->count();
        
        if ($customerCount > 0) {
            return redirect()->route('admin.territories.index')
                ->with('error', "Cannot delete territory. It is currently used by {$customerCount} customer(s).");
        }

        $territory->delete();

        return redirect()->route('admin.territories.index')
            ->with('success', 'Territory deleted successfully!');
    }
}
