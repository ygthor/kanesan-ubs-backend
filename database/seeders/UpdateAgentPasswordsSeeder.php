<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class UpdateAgentPasswordsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * This seeder updates all agent passwords to "password"
     */
    public function run(): void
    {
        // Update all users' passwords to "password"
        // If you want to update only users with a specific role, uncomment and modify the query below
        $users = User::all();
        
        // Alternative: Update only users with 'agent' role (if agent role exists)
        // $agentRole = DB::table('roles')->where('name', 'agent')->first();
        // if ($agentRole) {
        //     $userIds = DB::table('user_roles')
        //         ->where('role_id', $agentRole->role_id)
        //         ->pluck('user_id')
        //         ->toArray();
        //     $users = User::whereIn('id', $userIds)->get();
        // }
        
        $updatedCount = 0;
        foreach ($users as $user) {
            $user->password = Hash::make('password');
            $user->save();
            $updatedCount++;
        }
        
        $this->command->info("Updated {$updatedCount} user passwords to 'password'");
    }
}

