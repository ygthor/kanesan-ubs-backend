<?php

namespace App\Http\Controllers\Api\UBS;

use App\Http\Controllers\Controller;
use App\Models\UBS\ArCust;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $q = ArCust::query();
        // $q->where('CUSTNO', '=', '3000/U01');
        // $q->limit(1);
        $data = $q->get();
        return response()->json($data);
    }

    /**
     * @bodyParam CUSTNO string required The user's email. Example: 3000/U01
     * @bodyParam NAME string required The user's password. Example: UBS SOFTWARE
     */
    public function store(Request $request)
    {
        $CUSTNO = $request->input('CUSTNO');
        $NAME = $request->input('NAME');

        $request->validate(
            [
                'CUSTNO' => "required",
                'NAME' => "required",
            ],
            [],
            [
                'CUSTNO' => 'Customer Number',
                'NAME' => 'Customer Name',
            ]
        );

        $insert_arr = [
            'CUSTNO' => $CUSTNO,
            'GROUPTO' => $CUSTNO,
            'NAME' => $NAME,
            'CURRENCY' => 'RM',
            'CURRENCY1' => 'RINGGIT MALAYSIA',
            'CREATED_ON' => '',
            'UPDATED_ON' => '',
            'UPDATED_BY' => '',
            'CREATED_BY' => '',
            'AUTOPAY' => 'B',
            'EDITED' => 'Y',
            'PROV_DISC' => 0,
            'FOOT_DISC' => 0,
            'TERM_IN_M' => 0,
            'ACCSTATUS' => 'Y',


        ];
        $data = ArCust::create($insert_arr);
        return makeResponse(200, 'Customer created successfully', $data);
    }
}
