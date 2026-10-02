<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Stancl\Tenancy\Database\Models\Tenant;

class TenantSeeder extends Seeder
{
    public function run(): void
    {
        $cabang = [
            ['id' => 'hq', 'nama_cabang' => 'Head Office', 'kode_cabang' => 'HQ'],
            ['id' => 'plant-1', 'nama_cabang' => 'Plant 1', 'kode_cabang' => 'P01'],
        ];

        foreach ($cabang as $data) {
            Tenant::firstOrCreate(['id' => $data['id']], [
                'data' => array_diff_key($data, ['id' => null]),
            ]);
        }
    }
}
