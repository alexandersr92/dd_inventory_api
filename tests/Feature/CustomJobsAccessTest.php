<?php

namespace Tests\Feature;

use App\Models\Module;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\CustomJobs;
use Database\Seeders\CustomJobsAccessSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CustomJobsAccessTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
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
        Route::get('/api/v1/custom-jobs/test-materials', fn () => response()->json(['ok' => true]))
            ->middleware(['auth:sanctum', 'tenant.switch', 'custom_jobs:custom_job.production,products,inventories']);
        Route::post('/api/v1/custom-jobs/test-annul', fn () => response()->json(['ok' => true]))
            ->middleware(['auth:sanctum', 'tenant.switch', 'custom_jobs:custom_job.annul_payment']);
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

    public function test_missing_inactive_and_globally_disabled_module_deny_owner(): void
    {
        [$user, $org] = $this->account();
        $this->authenticate($user);
        $this->getJson('/api/v1/custom-jobs/access')->assertForbidden()->assertJsonPath('code', 'CUSTOM_JOBS_INACTIVE');
        $module = $this->activate($org);
        $this->authenticate($user);
        $this->getJson('/api/v1/custom-jobs/access')->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $org->modules()->updateExistingPivot($module->id, ['status' => 'inactive']);
        $this->authenticate($user);
        $this->getJson('/api/v1/custom-jobs/access')->assertForbidden();
        $org->modules()->updateExistingPivot($module->id, ['status' => 'active']);
        $module->update(['status' => 'inactive']);
        $this->authenticate($user);
        $this->getJson('/api/v1/custom-jobs/access')->assertForbidden();
    }

    public function test_permissions_and_revocation_are_checked_again(): void
    {
        [$user, $org, $role] = $this->account();
        $this->activate($org);
        $this->authenticate($user);
        $response = $this->getJson('/api/v1/custom-jobs/access')->assertOk();
        $this->assertFalse($response->json()['capabilities']['custom_job.costs']);
        $response->assertJsonMissing(['cost' => 10]);
        $role->revokePermissionTo('custom_job.index');
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/custom-jobs/access')->assertForbidden()->assertJsonPath('code', 'CUSTOM_JOBS_FORBIDDEN');
    }

    public function test_legacy_pos_tokens_and_client_flags_cannot_grant_access(): void
    {
        [$user, $org] = $this->account();
        $this->activate($org);
        $this->authenticate($user, ['*']);
        $this->withHeader('X-Client', 'admin')->getJson('/api/v1/custom-jobs/access?admin=true')
            ->assertForbidden()->assertJsonPath('code', 'CUSTOM_JOBS_ADMIN_SESSION_REQUIRED');
        $this->authenticate($user, ['pos']);
        $this->getJson('/api/v1/custom-jobs/access')->assertForbidden();
    }

    public function test_annulment_requires_cancellation_permission_too(): void
    {
        [$user, $org, $role] = $this->account(['custom_job.index', 'custom_job.annul_payment']);
        $this->activate($org);
        $this->authenticate($user);
        $response = $this->getJson('/api/v1/custom-jobs/access')->assertOk();
        $this->assertFalse($response->json()['capabilities']['custom_job.annul_payment']);
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/v1/custom-jobs/test-annul')->assertForbidden();
        $role->givePermissionTo('custom_job.cancel');
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/v1/custom-jobs/test-annul')->assertOk();
    }

    public function test_module_and_foreign_roles_do_not_cross_accounts(): void
    {
        [$userA, $orgA, $roleA] = $this->account();
        $this->activate($orgA);
        [$userB, $orgB, $roleB] = $this->account([]);
        $userB->assignRole($roleA);
        $this->authenticate($userB);
        $this->getJson('/api/v1/custom-jobs/access?organization_id='.$orgA->id)->assertForbidden();
        $this->activate($orgB);
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/custom-jobs/access')->assertForbidden()->assertJsonPath('code', 'CUSTOM_JOBS_FORBIDDEN');
    }

    public function test_seeder_is_additive_idempotent_and_never_assigns_module(): void
    {
        [$user, $org, $role] = $this->account();
        $other = Permission::create(['name' => 'custom.existing', 'display_name' => 'Existente', 'guard_name' => 'web']);
        $role->givePermissionTo($other);
        $restricted = Role::create(['name' => 'Diseñador', 'organization_id' => $org->id, 'guard_name' => 'web']);
        $restricted->givePermissionTo($other);
        $this->seed(CustomJobsAccessSeeder::class);
        $this->seed(CustomJobsAccessSeeder::class);
        $this->assertTrue($role->fresh()->hasPermissionTo('custom.existing'));
        $this->assertTrue($role->fresh()->hasPermissionTo('custom_job.costs'));
        $this->assertSame(['custom.existing'], $restricted->fresh()->permissions->pluck('name')->all());
        $this->assertSame(0, $org->modules()->count());
        $this->assertFalse(Module::availableByDefault()->where('slug', 'custom_jobs')->exists());
        $this->assertSame(11, Permission::where('name', 'like', 'custom_job.%')->count());
    }

    public function test_unauthenticated_or_tenantless_session_has_no_access(): void
    {
        $this->getJson('/api/v1/custom-jobs/access')->assertUnauthorized();
        $user = User::create(['name' => 'Sin cuenta', 'email' => fake()->safeEmail(), 'password' => 'password']);
        $this->authenticate($user);
        $this->getJson('/api/v1/custom-jobs/access')->assertForbidden()->assertJsonPath('code', 'CUSTOM_JOBS_INACTIVE');
    }

    public function test_reseeding_preserves_disabled_account_and_system_module(): void
    {
        [$user, $org] = $this->account();
        $module = $this->activate($org);
        $org->modules()->updateExistingPivot($module->id, ['status' => 'inactive']);
        $module->update(['status' => 'inactive']);
        $this->seed(CustomJobsAccessSeeder::class);
        $this->assertSame('inactive', $module->fresh()->status);
        $this->assertSame('inactive', $org->modules()->first()->pivot->status);
    }

    public function test_client_tenant_parameter_cannot_change_capabilities_or_dependencies(): void
    {
        [$userA, $orgA] = $this->account();
        [$userB, $orgB] = $this->account();
        $this->activate($orgA);
        $module = Module::create(['slug' => 'products', 'name' => 'Productos', 'status' => 'active']);
        $orgB->modules()->attach($module->id, ['status' => 'active']);
        $this->authenticate($userA);
        $this->getJson('/api/v1/custom-jobs/access?organization_id='.$orgB->id)
            ->assertOk()->assertJsonPath('organization_id', $orgA->id)->assertJsonPath('dependencies.products', false);
        $this->assertTrue(Module::availableByDefault()->where('slug', 'products')->exists());
    }

    public function test_action_dependencies_are_checked_without_automatic_activation(): void
    {
        [$user, $org] = $this->account(['custom_job.index', 'custom_job.production']);
        $this->activate($org);
        $this->authenticate($user);
        $this->getJson('/api/v1/custom-jobs/test-materials')->assertForbidden()
            ->assertJsonPath('code', 'CUSTOM_JOBS_DEPENDENCY_INACTIVE');
        foreach (['products', 'inventories'] as $slug) {
            $module = Module::create(['name' => $slug, 'slug' => $slug, 'status' => 'active']);
            $org->modules()->attach($module->id, ['status' => 'active']);
        }
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/custom-jobs/test-materials')->assertOk();
        $org->modules()->updateExistingPivot($module->id, ['status' => 'inactive']);
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/custom-jobs/test-materials')->assertForbidden();
    }

    public function test_admin_login_issues_explicit_scope_but_pos_login_does_not(): void
    {
        [$user] = $this->account();
        $credentials = ['email' => $user->email, 'password' => 'password', 'device_name' => 'web', 'client' => 'admin', 'abilities' => ['admin:custom_jobs']];
        $this->postJson('/api/v1/login', $credentials)->assertOk();
        $this->assertSame(['*'], $user->tokens()->latest('id')->first()->abilities);
        $this->postJson('/api/v1/admin/login', $credentials)->assertOk();
        $this->assertContains(CustomJobs::ADMIN_ABILITY, $user->tokens()->latest('id')->first()->abilities);
        $this->postJson('/api/v1/admin/login', array_merge($credentials, ['password' => 'wrong']))->assertUnprocessable();
    }
}
