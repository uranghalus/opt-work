<?php

namespace Database\Seeders;

use App\Models\Tenant;
use Illuminate\Database\Seeder;

class TenantSeeder extends Seeder
{
    public function run(): void
    {
        $cabang = [
            ['optigate_company_id' => 1, 'name' => 'Head Office', 'code' => 'hq', 'is_active' => true],
            ['optigate_company_id' => 2, 'name' => 'Plant 1', 'code' => 'plant-1', 'is_active' => true],
        ];

        foreach ($cabang as $data) {
            // `code` is the URL slug and lookup key; the ULID id is generated
            // by the model, `optigate_company_id` is the sync mapping key.
            Tenant::firstOrCreate(['code' => $data['code']], $data);
        }
    }
}
