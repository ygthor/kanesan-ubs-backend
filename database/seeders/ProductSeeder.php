<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Read products.json file
        $jsonPath = database_path('../../db/products.json');
        
        if (!File::exists($jsonPath)) {
            $this->command->error("products.json file not found at: {$jsonPath}");
            return;
        }
        
        $json = File::get($jsonPath);
        $products = json_decode($json, true);
        
        if (!$products || !is_array($products)) {
            $this->command->error("Invalid JSON format in products.json");
            return;
        }
        
        $this->command->info("Found " . count($products) . " products in JSON file");
        
        // Extract unique groups for product_groups table (optional, for reference)
        $groups = array_unique(array_column($products, 'GROUP'));
        $this->command->info("Found " . count($groups) . " unique product groups");
        
        // Insert groups into product_groups table (for reference, not required for products table)
        foreach ($groups as $groupName) {
            if (empty($groupName)) {
                continue;
            }
            
            $group = DB::table('icgroup')
                ->where('name', $groupName)
                ->first();
            
            if (!$group) {
                DB::table('icgroup')->insert([
                    'name' => $groupName,
                    'description' => null,
                    'CREATED_ON' => now(),
                    'UPDATED_ON' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $this->command->info("Created product group: {$groupName}");
            }
        }
        
        // Insert products (using group_name directly for UBS compatibility)
        $inserted = 0;
        $skipped = 0;
        
        foreach ($products as $product) {
            $code = $product['CODE'] ?? null;
            $description = $product['DESCRIPTION'] ?? null;
            $groupName = $product['GROUP'] ?? null;
            
            if (empty($code)) {
                $this->command->warn("Skipping product with empty CODE");
                $skipped++;
                continue;
            }
            
            if (empty($groupName)) {
                $this->command->warn("Skipping product {$code}: group name is empty");
                $skipped++;
                continue;
            }
            
            $exists = DB::table('products')
                ->where('code', $code)
                ->exists();
            
            if ($exists) {
                // Update existing product
                DB::table('products')
                    ->where('code', $code)
                    ->update([
                        'description' => $description,
                        'group_name' => $groupName,
                        'UPDATED_ON' => now(),
                        'updated_at' => now(),
                    ]);
                $this->command->line("Updated product: {$code}");
            } else {
                // Insert new product
                DB::table('products')->insert([
                    'code' => $code,
                    'description' => $description,
                    'group_name' => $groupName,
                    'is_active' => 1,
                    'CREATED_ON' => now(),
                    'UPDATED_ON' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $inserted++;
            }
        }
        
        $this->command->info("Import completed!");
        $this->command->info("Inserted/Updated: {$inserted} products");
        if ($skipped > 0) {
            $this->command->warn("Skipped: {$skipped} products");
        }
    }
}

