<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Territory;
use Illuminate\Http\Request;

class TerritoryController extends Controller
{
    /**
     * Display a listing of territories.
     */
    public function index()
    {
        try {
            $territories = Territory::orderBy('area')->get();

            return makeResponse(200, 'Territories retrieved successfully', $territories);
        } catch (\Exception $e) {
            return makeResponse(500, 'Failed to retrieve territories: ' . $e->getMessage(), []);
        }
    }

    /**
     * Display the specified territory.
     */
    public function show($id)
    {
        try {
            $territory = Territory::findOrFail($id);

            return makeResponse(200, 'Territory retrieved successfully', $territory);
        } catch (\Exception $e) {
            return makeResponse(404, 'Territory not found', []);
        }
    }
}
