<?php

namespace Database\Seeders;

use App\Models\Tenant;
use Illuminate\Database\Seeder;

class TenantSeeder extends Seeder
{
    public function run(): void
    {
        $cabang = [
            ['id' => 'hq', 'name' => 'Head Office', 'code' => 'HQ', 'is_active' => true],
            ['id' => 'plant-1', 'name' => 'Plant 1', 'code' => 'P01', 'is_active' => true],
        ];

        foreach ($cabang as $data) {
            // Columns are passed as top-level attributes. Handing them to `data`
            // instead does nothing: VirtualColumn strips the `data` attribute on
            // creating and rewrites it from the remaining virtual attributes.
            Tenant::firstOrCreate(['id' => $data['id']], $data);
        }
    }
}
