<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class SyncController extends Controller
{

    public function syncLocalData(Request $request)
    {
        $directory = $request->input('directory');
        $filename = $request->input('filename');
        $dbf_data = $request->input('data');

        $structures = $dbf_data['structure'];
        $rows = $dbf_data['rows'];

        $filename = explode('.', $filename)[0];
        $prefix = "ubs";
        $directory_name = strtolower($directory);
        $tableName = "{$prefix}_{$directory_name}_{$filename}";

        $this->createTable($tableName, $structures);
        foreach ($rows as $d) {
            DB::table($tableName)->insert($d);
        }


        return response()->json([
            'message' => "Received $directory / $filename successfully",
            // 'data' => $data
        ], 201);
    }

    public function createTable($tableName, $structures)
    {
        if (!Schema::hasTable($tableName)) {
            Schema::create($tableName, function (Blueprint $table) use ($structures) {
                foreach ($structures as $structure) {
                    $name = $structure['name']; // normalize to safe column name
                    $type = $structure['type'];
                    $size = $structure['size'] ?? null;
                    $decs = $structure['decs'] ?? null;


                    switch ($type) {
                        case 'D': // Date
                            $table->string($name)->nullable();
                            break;

                        case 'T': // DateTime/Timestamp
                            $table->string($name)->nullable();
                            break;

                        case 'C': // Character/String
                            if ($size > 255) {
                                $table->text($name)->nullable();
                            } else {
                                $table->string($name, $size)->nullable();
                            }
                            break;

                        case 'N': // Numeric
                            // Ensure decimals are handled correctly
                            if ($decs > $size) {
                                // Adjust precision and scale (e.g., use DECIMAL(10, 2) if required)
                                $table->decimal($name, $size ?? 10, $decs ?? 2)->nullable();
                            } else {
                                $table->integer($name)->nullable();
                            }
                            break;

                        case 'F': // Float
                            $table->float($name)->nullable();
                            break;

                        case 'L': // Logical/Boolean
                            $table->boolean($name)->nullable();
                            break;

                        default:
                            $table->text($name)->nullable(); // fallback
                            break;
                    }
                }
            });
        }
    }
}
