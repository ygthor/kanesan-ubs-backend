<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Territory;

class TerritorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $territories = [
            ['area' => 'AYER TAWAR', 'description' => 'AYER TAWAR'],
            ['area' => 'BAGAN SERAI', 'description' => 'BAGAN SERAI'],
            ['area' => 'BRUAS', 'description' => 'BRUAS'],
            ['area' => 'CAMERON HIGL', 'description' => 'CAMERON HIGHLAND'],
            ['area' => 'GERIK', 'description' => 'GERIK'],
            ['area' => 'IPOH', 'description' => 'IPOH'],
            ['area' => 'K.KANGSAR', 'description' => 'K.KANGSAR'],
            ['area' => 'KAMPAR', 'description' => 'KAMPAR'],
            ['area' => 'KAMUNTING', 'description' => 'KAMUNTING'],
            ['area' => 'LANGKAP', 'description' => 'LANGKAP'],
            ['area' => 'LUMUT', 'description' => 'LUMUT'],
            ['area' => 'MANJUNG', 'description' => 'MANJUNG'],
            ['area' => 'P.REMIS', 'description' => 'P.REMIS'],
            ['area' => 'PANGKOR', 'description' => 'PANGKOR'],
            ['area' => 'PARIT BUNTAR', 'description' => 'PARIT BUNTAR'],
            ['area' => 'S.SIPUT', 'description' => 'S.SIPUT'],
            ['area' => 'SABAK BERNAM', 'description' => 'SABAK BERNAM'],
            ['area' => 'SELAMA', 'description' => 'SELAMA'],
            ['area' => 'SEMANGGOL', 'description' => 'SEMANGGOL'],
            ['area' => 'SG PETANI', 'description' => 'SG PETANI'],
            ['area' => 'SIMPANG', 'description' => 'SIMPANG'],
            ['area' => 'SITIAWAN', 'description' => 'SITIAWAN'],
            ['area' => 'SRI ISKANDAR', 'description' => 'SRI ISKANDAR'],
            ['area' => 'TAIPING', 'description' => 'TAIPING'],
            ['area' => 'TAPAH', 'description' => 'TAPAH'],
            ['area' => 'TELUK INTAN', 'description' => 'TELUK INTAN'],
            ['area' => 'TG. MALIM', 'description' => 'TG. MALIM'],
        ];

        foreach ($territories as $territory) {
            Territory::updateOrCreate(
                ['area' => $territory['area']],
                ['description' => $territory['description']]
            );
        }
    }
}
