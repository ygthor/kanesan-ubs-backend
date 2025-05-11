<?php

function makeResponse($status_code, $message = "", $data =[])
{
    return response()->json([
        'status' => $status_code,
        'message' => $message,
        'data' => $data,
    ]);
}