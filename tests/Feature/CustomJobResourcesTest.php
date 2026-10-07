<?php

namespace Tests\Feature;

use App\Models\Module;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Services\TenantManager;
use App\Support\CustomJobs;
use Database\Seeders\CustomJobsAccessSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CustomJobResourcesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        TenantManager::clear();
        // A fresh in-memory central database; never migrate or seed a developer database.
        config(['database.connections.central' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true]]);
        DB::purge('central');
        DB::setDefaultConnection('central');
        Schema::create('users', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('name');
            $t->string('email')->unique();
            $t->string('password');
            $t->uuid('organization_id')->nullable();
            $t->uuid('seller_id')->nullable();
            $t->uuid('role_id')->nullable();
            $t->string('status')->default('active');
            $t->boolean('must_change_password')->default(false);
            $t->timestamp('email_verified_at')->nullable();
            $t->rememberToken();
            $t->timestamps();
        });
        Schema::create('organizations', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('name');
            $t->string('email')->nullable();
            $t->uuid('owner_id');
            $t->string('status')->default('active');
            $t->boolean('is_lifetime')->default(true);
            $t->timestamp('license_expires_at')->nullable();
            $t->string('tenancy_type')->default('shared');
            $t->softDeletes();
            $t->timestamps();
        });
        foreach (['2024_08_15_162057_create_modules_table.php', '2026_06_25_145832_add_slug_to_modules_table.php',
            '2024_08_15_165102_create_organization_module_table.php', '2024_09_03_062911_create_permission_tables.php',
            '2024_08_13_050715_create_personal_access_tokens_table.php'] as $migration) {
            (require database_path('migrations/'.$migration))->up();
        }
        Schema::table('roles', function (Blueprint $t) {
            $t->dropUnique('roles_name_guard_name_unique');
            $t->unique(['name', 'guard_name', 'organization_id']);
        });
        Schema::create('stores', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('name');
        });
        Schema::create('user_store', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('user_id');
            $t->uuid('store_id');
            $t->string('status')->default('active');
        });
        $this->seed(CustomJobsAccessSeeder::class);
        (require database_path('migrations/2026_10_06_000000_create_custom_job_resources.php'))->up();
    }

    private function account(array $permissions = ['custom_job.index']): array
    {
        $user = User::create(['name' => 'Usuario', 'email' => fake()->unique()->safeEmail(), 'password' => 'password']);
        $org = Organization::create(['name' => 'Cuenta', 'owner_id' => $user->id, 'is_lifetime' => true]);
        $user->update(['organization_id' => $org->id]);
        $role = Role::firstOrCreate(['name' => 'Owner', 'organization_id' => $org->id], ['guard_name' => 'web']);
        $role->givePermissionTo($permissions);
        $user->assignRole($role);

        return [$user, $org, $role];
    }

    private function activate(Organization $org): Module
    {
        $module = Module::where('slug', 'custom_jobs')->firstOrFail();
        $org->modules()->attach($module->id, ['status' => 'active']);

        return $module;
    }

    private function authenticate(User $user, array $abilities = ['*', CustomJobs::ADMIN_ABILITY]): void
    {
        $this->withToken($user->createToken('test', $abilities)->plainTextToken);
        // Simulate separate HTTP requests rather than reusing Sanctum's cached user.
        $this->app['auth']->forgetGuards();
    }

    private function startAccount(array $permissions = ['custom_job.index', 'custom_job.costs', 'custom_job.configure']): array
    {
        [$user, $org, $role] = $this->account($permissions);
        $this->activate($org);
        $this->authenticate($user);

        return [$user, $org, $role];
    }

    private function request(string $method, string $path, array $data = [])
    {
        $this->app['auth']->forgetGuards();

        return $this->json($method, '/api/v1/custom-jobs/'.$path, $data);
    }

    private function process(): array
    {
        return $this->request('POST', 'resources/processes', ['name' => 'Diseño', 'status' => 'active', 'configuration' => ['kind' => 'digital']])->assertCreated()->json('data');
    }

    private function machine(array $config = []): array
    {
        return $this->request('POST', 'resources/machines', ['name' => 'Láser', 'status' => 'active', 'configuration' => array_merge([
            'method' => 'manual', 'minute_rate' => 2, 'purchase_value' => 1200, 'residual_value' => 200,
            'useful_minutes' => 1000, 'maintenance_per_minute' => 0.1, 'energy_per_minute' => 0.2,
        ], $config)])->assertCreated()->json('data');
    }

    private function rate(string $processId, array $config = []): array
    {
        return $this->request('POST', 'resources/rates', ['name' => 'Corte', 'status' => 'active', 'process_id' => $processId,
            'configuration' => array_merge(['unit' => 'unit', 'amount' => 3, 'preparation_cost' => 5, 'included' => [], 'ink_unit' => 'none', 'ink_rate' => 1], $config),
        ])->assertCreated()->json('data');
    }

    private function selection(array $overrides = []): array
    {
        return array_merge(['rate_id' => null, 'machine_id' => null, 'rate_quantity' => 100, 'machine_minutes' => 10, 'ink_quantity' => 10,
            'people' => [['count' => 2, 'method' => 'hour', 'scope' => 'person', 'minutes' => 10, 'amount' => 60]]], $overrides);
    }

    public function test_hourly_labor_team_payment_and_person_payment(): void
    {
        $this->startAccount();
        $this->request('POST', 'resource-preview', $this->selection())->assertOk()->assertJsonPath('data.total', 20);
        foreach (['team' => 100, 'person' => 300] as $scope => $expected) {
            $this->request('POST', 'resource-preview', $this->selection(['people' => [['count' => 3, 'method' => 'work', 'scope' => $scope, 'minutes' => 0, 'amount' => 100]]]))
                ->assertOk()->assertJsonPath('data.total', $expected);
        }
    }

    public function test_machine_components_setup_ink_and_included_costs_are_not_duplicated(): void
    {
        $this->startAccount();
        $process = $this->process();
        $machine = $this->machine();
        $rate = $this->rate($process['id']);
        $selection = $this->selection(['rate_id' => $rate['id'], 'machine_id' => $machine['id']]);
        // Laser never adds ink; preparation is once for 100 items.
        $this->request('POST', 'resource-preview', $selection)->assertOk()->assertJsonPath('data.total', 348)
            ->assertJsonPath('data.ink_cost', 0)->assertJsonPath('data.preparation_cost', 5)->assertJsonPath('data.labor_cost', 20);
        $rate = $this->rate($process['id'], ['included' => ['operator', 'maintenance', 'electricity'], 'ink_unit' => 'page', 'ink_rate' => 0.5]);
        $this->request('POST', 'resource-preview', [...$selection, 'rate_id' => $rate['id']])->assertOk()
            ->assertJsonPath('data.total', 330)->assertJsonPath('data.labor_cost', 0)->assertJsonPath('data.machine_cost', 20)->assertJsonPath('data.ink_cost', 5);
        $rate = $this->rate($process['id'], ['unit' => 'job', 'amount' => 100, 'included' => ['operator', 'machine', 'ink'], 'ink_unit' => 'page']);
        $this->request('POST', 'resource-preview', [...$selection, 'rate_id' => $rate['id']])->assertOk()
            ->assertJsonPath('data.total', 105)->assertJsonPath('data.machine_cost', 0)->assertJsonPath('data.ink_cost', 0);
    }

    public function test_depreciation_snapshot_is_a_copy_and_archived_resources_cannot_be_selected(): void
    {
        $this->startAccount();
        $process = $this->process();
        $rate = $this->rate($process['id']);
        $machine = $this->machine(['method' => 'depreciation']);
        $selection = $this->selection(['rate_id' => $rate['id'], 'machine_id' => $machine['id']]);
        $snapshot = $this->request('POST', 'resource-preview', $selection)->assertOk()->assertJsonPath('data.machine_cost', 13)->json('data.snapshot');
        $updated = ['name' => 'Nueva tarifa', 'status' => 'active', 'process_id' => $process['id'], 'configuration' => [...$rate['configuration'], 'amount' => 10]];
        $this->request('PUT', 'resources/rates/'.$rate['id'], $updated)->assertOk();
        $this->assertSame(3, $snapshot['rate']['configuration']['amount']);
        $this->assertSame('Corte', $snapshot['rate']['name']);
        $this->request('POST', 'resources/machines/'.$machine['id'].'/archive')->assertOk();
        $this->assertSame('active', $snapshot['machine']['status']);
        $this->assertDatabaseHas('custom_job_machines', ['id' => $machine['id'], 'status' => 'archived']);
        $this->request('POST', 'resource-preview', $selection)->assertUnprocessable()->assertJsonValidationErrors('machine_id');
        $this->request('POST', 'resources/processes/'.$process['id'].'/archive')->assertOk();
        $this->request('POST', 'resource-preview', [...$selection, 'machine_id' => null])->assertUnprocessable()->assertJsonValidationErrors('process_id');
    }

    public function test_costs_permissions_revocation_module_and_pos_sessions(): void
    {
        [$user, $org, $role] = $this->startAccount();
        $machine = $this->machine();
        $role->revokePermissionTo('custom_job.costs');
        $this->request('GET', 'resources/machines')->assertOk()->assertJsonMissingPath('data.0.configuration');
        $this->request('POST', 'resource-preview', $this->selection())->assertForbidden();
        $this->request('POST', 'resources/machines/'.$machine['id'].'/archive')->assertForbidden();
        $role->givePermissionTo('custom_job.costs');
        $role->revokePermissionTo('custom_job.configure');
        $this->request('POST', 'resources/machines/'.$machine['id'].'/archive')->assertForbidden();
        $this->request('POST', 'resource-preview', $this->selection())->assertOk();
        $this->authenticate($user, ['*']);
        $this->request('GET', 'resources/machines')->assertForbidden();
        $this->authenticate($user);
        $org->modules()->where('slug', 'custom_jobs')->first()->pivot->update(['status' => 'inactive']);
        $this->request('GET', 'resources/machines')->assertForbidden();
    }

    public function test_foreign_catalogs_cannot_be_read_changed_referenced_or_previewed(): void
    {
        [$userA, $orgA] = $this->startAccount();
        $process = $this->process();
        $machine = $this->machine();
        $rate = $this->rate($process['id']);
        $this->startAccount();
        $this->request('GET', 'resources/machines?organization_id='.$orgA->id)->assertOk()->assertJsonCount(0, 'data');
        $this->request('POST', 'resources/machines/'.$machine['id'].'/archive')->assertNotFound();
        $this->request('PUT', 'resources/processes/'.$process['id'], ['name' => 'Otra'])->assertNotFound();
        $this->request('POST', 'resources/rates', ['name' => 'Ajena', 'status' => 'active', 'process_id' => $process['id'], 'configuration' => $rate['configuration']])
            ->assertUnprocessable()->assertJsonValidationErrors('process_id');
        $this->request('POST', 'resource-preview', $this->selection(['machine_id' => $machine['id']]))->assertNotFound();
        $this->request('POST', 'resource-preview', $this->selection(['rate_id' => $rate['id']]))->assertNotFound();
    }

    public function test_validation_precision_and_digital_charge_has_no_inventory_or_files(): void
    {
        $this->startAccount();
        $process = $this->process();
        $this->assertDatabaseCount('custom_job_processes', 1);
        $this->assertFalse(Schema::hasTable('products'));
        $this->assertSame(['kind' => 'digital'], $process['configuration']);
        $invalidMachine = ['name' => 'Inválida', 'status' => 'active', 'configuration' => ['method' => 'depreciation', 'minute_rate' => -1, 'purchase_value' => 100,
            'residual_value' => 200, 'useful_minutes' => 0, 'maintenance_per_minute' => 0, 'energy_per_minute' => 0]];
        $this->request('POST', 'resources/machines', $invalidMachine)->assertUnprocessable()
            ->assertJsonValidationErrors(['configuration.minute_rate', 'configuration.residual_value', 'configuration.useful_minutes']);
        $this->request('POST', 'resource-preview', $this->selection(['people' => [['count' => 1.5, 'method' => 'hour', 'scope' => 'team', 'minutes' => -1, 'amount' => -1]]]))
            ->assertUnprocessable()->assertJsonValidationErrors(['people.0.count', 'people.0.minutes', 'people.0.amount']);
        $this->request('POST', 'resource-preview', $this->selection(['people' => [['count' => 1, 'method' => 'hour', 'scope' => 'team', 'minutes' => 10, 'amount' => 1]]]))
            ->assertUnprocessable()->assertJsonValidationErrors('people.0.scope');
        $measured = $this->rate($process['id'], ['ink_unit' => 'measured', 'ink_rate' => 0.125, 'ink_measurement_unit' => 'ml']);
        $this->request('POST', 'resource-preview', $this->selection(['rate_id' => $measured['id'], 'ink_quantity' => 0.5, 'people' => []]))
            ->assertOk()->assertJsonPath('data.ink_cost', 0.0625)->assertJsonPath('data.snapshot.rate.configuration.ink_measurement_unit', 'ml');
        $this->request('POST', 'resources/rates', ['name' => 'Unidad faltante', 'status' => 'active', 'process_id' => $process['id'],
            'configuration' => [...$measured['configuration'], 'ink_measurement_unit' => '']])->assertUnprocessable()->assertJsonValidationErrors('configuration.ink_measurement_unit');
        $rate = $this->rate($process['id'], ['unit' => 'cm2', 'amount' => 0.00000001, 'preparation_cost' => 0]);
        $this->request('POST', 'resource-preview', $this->selection(['rate_id' => $rate['id'], 'rate_quantity' => 100, 'people' => []]))
            ->assertOk()->assertJsonPath('data.process_cost', 0.000001);
        $this->request('POST', 'resources/processes', ['name' => 'Archivo', 'status' => 'active', 'configuration' => ['kind' => 'digital', 'file' => 'file.pdf']])
            ->assertUnprocessable();
        $this->request('GET', 'resources/unknown')->assertNotFound();
    }
}
