<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class TimeTestController extends Controller
{
    /**
     * Test endpoint to check server, PHP, and MySQL timezone settings
     */
    public function index()
    {
        // PHP time using Carbon
        $phpNow = Carbon::now();
        $phpNowUtc = Carbon::now('UTC');
        
        // PHP timezone setting
        $phpTimezone = date_default_timezone_get();
        $appTimezone = config('app.timezone');
        
        // MySQL time
        $mysqlNow = DB::select('SELECT NOW() as mysql_time, @@global.time_zone as global_timezone, @@session.time_zone as session_timezone')[0];
        
        // Server time
        $serverTime = date('Y-m-d H:i:s');
        $serverTimezone = date_default_timezone_get();
        
        return response()->json([
            'error' => 0,
            'status' => 200,
            'message' => 'Time test information',
            'data' => [
                'php' => [
                    'now' => $phpNow->format('Y-m-d H:i:s'),
                    'now_timezone' => $phpNow->timezone->getName(),
                    'now_utc' => $phpNowUtc->format('Y-m-d H:i:s'),
                    'php_timezone' => $phpTimezone,
                    'app_timezone_config' => $appTimezone,
                    'formatted' => $phpNow->format('Y-m-d H:i:s T'),
                ],
                'mysql' => [
                    'now' => $mysqlNow->mysql_time,
                    'global_timezone' => $mysqlNow->global_timezone,
                    'session_timezone' => $mysqlNow->session_timezone,
                ],
                'server' => [
                    'time' => $serverTime,
                    'timezone' => $serverTimezone,
                    'timestamp' => time(),
                ],
                'comparison' => [
                    'php_now' => $phpNow->format('Y-m-d H:i:s'),
                    'mysql_now' => $mysqlNow->mysql_time,
                    'difference_seconds' => strtotime($phpNow->format('Y-m-d H:i:s')) - strtotime($mysqlNow->mysql_time),
                ],
            ],
        ]);
    }
}

