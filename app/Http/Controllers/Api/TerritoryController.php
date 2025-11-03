<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Territory;
use Illuminate\Http\Request;
use App\Helper;

class TerritoryController extends Controller
{
    /**
     * Display a listing of territories.
     */
    public function index()
    {
        try {
            $territories = Territory::orderBy('area')->get();

            return Helper::makeResponse([
                'status' => 200,
                'message' => 'Territories retrieved successfully',
                'data' => $territories
            ]);
        } catch (\Exception $e) {
            return Helper::makeResponse([
                'status' => 500,
                'message' => 'Failed to retrieve territories: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    /**
     * Display the specified territory.
     */
    public function show($id)
    {
        try {
            $territory = Territory::findOrFail($id);

            return Helper::makeResponse([
                'status' => 200,
                'message' => 'Territory retrieved successfully',
                'data' => $territory
            ]);
        } catch (\Exception $e) {
            return Helper::makeResponse([
                'status' => 404,
                'message' => 'Territory not found',
                'data' => null
            ], 404);
        }
    }
}
