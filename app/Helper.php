<?php

function makeResponse($status_code, $message = "", $data =[])
{
    
    if($status_code == 200 || $status_code == 201){
        $error = 0;
    }else{
        $error = 1;
    }
    return response()->json([
        'error' => $error,
        'status' => $status_code,
        'message' => $message,
        'data' => $data,
    ]);
}