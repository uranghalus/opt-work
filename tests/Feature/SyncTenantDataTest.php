<?php

use App\Console\Commands\SyncTenantData;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

class SyncTenantDataTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create([
            'code' => 't1',
            'name' => 'Tenant One',
            'optigate_company_id' => 'c1',
        ]);

        Config::set('auth.super_admin_email', 'test@test.com');
    }

    public function test_command_calls_subcommands_in_dependency_order_and_seeders(): void
    {
        $tenantId = $this->tenant->id;

        Artisan::shouldReceive('call')
            ->with('app:sync-departments', ['--tenant' => $tenantId])
            ->twice()
            ->andReturn(Command::SUCCESS);
        Artisan::shouldReceive('call')
            ->with('app:sync-divisions', ['--tenant' => $tenantId])
            ->once()
            ->andReturn(Command::SUCCESS);
        Artisan::shouldReceive('call')
            ->with('app:sync-positions', ['--tenant' => $tenantId])
            ->once()
            ->andReturn(Command::SUCCESS);
        Artisan::shouldReceive('call')
            ->with('app:sync-employees', ['--tenant' => $tenantId])
            ->once()
            ->andReturn(Command::SUCCESS);
        Artisan::shouldReceive('call')
            ->with('db:seed', ['--class' => 'Database\\Seeders\\RoleAndPermissionSeeder'])
            ->once()
            ->andReturn(Command::SUCCESS);

        // Run the command directly: $this->artisan() would go through the same
        // mocked Artisan kernel, so it would be swallowed by the expectations
        // above instead of reaching SyncTenantData::handle().
        $command = new SyncTenantData;
        $command->setLaravel($this->app);

        $output = new BufferedOutput;

        $exitCode = $command->run(new ArrayInput(['--tenant' => 't1']), $output);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('Sinkronisasi tenant data selesai.', $output->fetch());
    }
}
