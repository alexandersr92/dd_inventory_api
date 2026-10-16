<?php

namespace Tests\Feature;

use App\Contracts\CustomJobReservation;
use App\Http\Resources\InvoiceResource;
use App\Models\Client;
use App\Models\CustomJobMaterialEvent;
use App\Models\CustomJobOrder;
use App\Models\CustomJobRate;
use App\Models\Inventory;
use App\Models\InventoryDetail;
use App\Models\InventoryMovement;
use App\Models\Invoice;
use App\Models\Module;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Store;
use App\Models\User;
use App\Services\CustomJobMaterials;
use App\Services\CustomJobOrders;
use App\Services\InventoryMovementService;
use App\Services\TenantManager;
use App\Support\CustomJobProforma;
use App\Support\CustomJobs;
use Database\Seeders\CustomJobsAccessSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class CustomJobCalculationTest extends TestCase
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
        (require database_path('migrations/2026_10_07_000000_create_custom_job_templates.php'))->up();
        Schema::table('stores', function (Blueprint $t) {
            $t->uuid('organization_id');
            $t->string('status')->default('active');
            $t->string('store_currency')->nullable();
            $t->timestamps();
        });
        foreach (['2024_09_07_213444_create_products_table.php', '2024_09_18_025336_create_inventories_table.php', '2024_09_18_025405_create_inventory_details_table.php', '2026_07_03_000000_create_inventory_store_table.php', '2025_05_09_013100_create_settings_table.php'] as $migration) {
            (require database_path('migrations/'.$migration))->up();
        }
        Schema::table('products', function (Blueprint $t) {
            $t->string('product_usage')->default('sale');
            $t->json('material_profile')->nullable();
            $t->decimal('cost', 20, 8)->change();
        });
        Schema::table('inventory_details', fn (Blueprint $t) => $t->decimal('quantity', 20, 6)->change());
        Schema::create('clients', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('organization_id');
            $t->string('name');
            $t->string('status')->default('active');
            $t->string('cedula_ruc')->nullable();
            $t->timestamps();
        });
        (require database_path('migrations/2024_11_08_040640_create_invoices_table.php'))->up();
        Schema::table('invoices', function (Blueprint $t) {
            $t->string('invoice_type')->nullable();
            $t->string('source')->nullable();
            $t->decimal('paid_in_nio', 20, 2)->default(0);
            $t->decimal('paid_in_usd', 20, 2)->default(0);
            $t->decimal('exchange_rate', 20, 8)->nullable();
            $t->uuid('cash_session_id')->nullable();
            $t->json('payment_metadata')->nullable();
        });
        Schema::table('stores', function (Blueprint $t) {
            $t->integer('invoice_number')->default(0);
            $t->string('invoice_prefix')->nullable();
        });
        (require database_path('migrations/2026_10_07_010000_create_custom_job_quotes.php'))->up();
        (require database_path('migrations/2026_10_10_000000_create_custom_job_orders.php'))->up();
        Schema::create('sellers', fn (Blueprint $t) => $t->uuid('id')->primary());
        (require database_path('migrations/2026_07_01_095815_create_inventory_movements_table.php'))->up();
        (require database_path('migrations/2026_10_10_010000_create_custom_job_material_ledger.php'))->up();
        (require database_path('migrations/2026_10_10_020000_create_custom_job_production.php'))->up();
        (require database_path('migrations/2026_10_11_000000_create_custom_job_deliveries.php'))->up();
        (require database_path('migrations/2026_10_11_010000_create_custom_job_order_revisions.php'))->up();
        Schema::create('cash_sessions', function(Blueprint $t) { $t->uuid('id')->primary(); $t->uuid('organization_id'); $t->uuid('store_id'); $t->uuid('user_id'); $t->string('status')->default('open'); $t->string('cash_register_name')->nullable(); $t->timestamp('opened_at')->nullable(); $t->timestamps(); });
        (require database_path('migrations/2026_10_15_000000_create_custom_job_payments.php'))->up();

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
        TenantManager::setTenant($org);
        foreach (['products', 'inventories'] as $slug) {
            $module = Module::firstOrCreate(['slug' => $slug], ['name' => $slug, 'status' => 'active']);
            $org->modules()->attach($module->id, ['status' => 'active']);
        }

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

    private function store(): Store
    {
        return Store::create(['name' => 'Sucursal', 'status' => 'active', 'store_currency' => 'C$']);
    }

    private function source(Store $store, string $name, float $cost, string $base = 'unit', ?array $profile = null, string $usage = 'both'): string
    {
        $product = Product::create(['organization_id' => $store->organization_id, 'name' => $name, 'cost' => $cost, 'price' => 999,
            'unit_of_measure' => $base, 'product_usage' => $usage, 'status' => 'active', 'material_profile' => $profile]);
        $inventory = Inventory::create(['name' => 'Bodega '.$name, 'store_id' => $store->id, 'status' => 'active']);

        $detail = new InventoryDetail(['product_id' => $product->id, 'inventory_id' => $inventory->id, 'quantity' => 1000, 'price' => 1999]);
        $detail->id = (string) Str::uuid();
        $detail->saveQuietly();

        return $detail->id;
    }

    private function material(string $id, array $overrides = []): array
    {
        return [...['inventory_detail_id' => $id, 'method' => 'unit', 'amount' => 1, 'width' => 8, 'height' => 6,
            'applications' => 1, 'measure_unit' => 'cm', 'quantity_source' => 'base', 'loss' => ['method' => 'none', 'value' => 0]], ...$overrides];
    }

    private function article(array $materials, array $operations = [], array $overrides = []): array
    {
        return [...['name' => 'Trabajo', 'quantity' => 1, 'materials' => $materials, 'operations' => $operations,
            'profit' => ['method' => 'percent', 'value' => 30, 'scope' => 'batch']], ...$overrides];
    }

    private function input(Store $store, array $articles, array $overrides = []): array
    {
        return [...['store_id' => $store->id, 'currency' => 'NIO', 'articles' => $articles, 'discount' => ['method' => 'amount', 'value' => 0], 'tax_amount' => 0], ...$overrides];
    }

    private function operation(string $machine, int $peopleMinutes = 10, float $hourRate = 60, int $people = 2): array
    {
        return ['selection' => [...$this->selection(['machine_id' => $machine, 'rate_quantity' => 1, 'machine_minutes' => 1, 'ink_quantity' => 0]),
            'people' => [['count' => $people, 'amount' => $hourRate, 'method' => 'hour', 'scope' => 'person', 'minutes' => $peopleMinutes, 'setup_minutes' => 0]]], 'machine_setup_minutes' => 0];
    }

    public function test_reference_mug_and_cup_use_inventory_cost_not_retail_and_never_write_stock(): void
    {
        $this->startAccount();
        $store = $this->store();
        $mug = $this->source($store, 'Taza', 80);
        $uv = $this->source($store, 'UV DTF', 0.1, 'cm2', null, 'supply');
        $paper = $this->source($store, 'Opalina', 1);
        $machine = $this->machine(['minute_rate' => 2.5, 'maintenance_per_minute' => 0, 'energy_per_minute' => 0]);
        $input = $this->input($store, [$this->article([$this->material($mug), $this->material($uv, ['method' => 'area']), $this->material($paper)], [$this->operation($machine['id'])])]);
        $snapshot = DB::table('inventory_details')->get()->toArray();
        $this->request('POST', 'calculation-preview', [...$input, 'total' => 0])->assertOk()->assertJsonPath('data.totals.cost', 108.3)->assertJsonPath('data.totals.total', 140.79)
            ->assertJsonPath('data.articles.0.materials.1.base_quantity', 48)->assertJsonPath('data.articles.0.ink_cost', 0);
        $this->assertEquals($snapshot, DB::table('inventory_details')->get()->toArray());
        $cup = $this->source($store, 'Vaso', 15);
        $vinyl = $this->source($store, 'Vinil', 0.08, 'cm2');
        $machine = $this->machine(['minute_rate' => 1.2, 'maintenance_per_minute' => 0, 'energy_per_minute' => 0]);
        $this->request('POST', 'calculation-preview', $this->input($store, [$this->article([$this->material($cup), $this->material($vinyl, ['method' => 'area'])], [$this->operation($machine['id'], 6, 60, 1)])]))
            ->assertOk()->assertJsonPath('data.totals.cost', 26.04)->assertJsonPath('data.totals.total', 33.85);
    }

    public function test_batch_setup_work_team_fixed_profit_and_included_costs(): void
    {
        $this->startAccount();
        $store = $this->store();
        $process = $this->process();
        $rate = $this->rate($process['id'], ['amount' => 0, 'preparation_cost' => 5]);
        $machine = $this->machine(['minute_rate' => 1, 'maintenance_per_minute' => 0, 'energy_per_minute' => 0]);
        $op = $this->operation($machine['id'], 1, 60, 1);
        $op['selection']['rate_id'] = $rate['id'];
        $op['machine_setup_minutes'] = 10;
        $op['selection']['people'][0]['setup_minutes'] = 5;
        $article = $this->article([], [$op], ['quantity' => 100, 'profit' => ['method' => 'amount', 'value' => 100, 'scope' => 'batch']]);
        $this->request('POST', 'calculation-preview', $this->input($store, [$article]))->assertOk()->assertJsonPath('data.totals.cost', 220)->assertJsonPath('data.totals.total', 320)
            ->assertJsonPath('data.articles.0.unit_price', 3.2)->assertJsonPath('data.articles.0.preparation_cost', 5);
        $op['selection']['people'] = [['count' => 3, 'method' => 'work', 'scope' => 'team', 'minutes' => 0, 'setup_minutes' => 0, 'amount' => 100]];
        $article['operations'] = [$op];
        $this->request('POST', 'calculation-preview', $this->input($store, [$article]))->assertOk()->assertJsonPath('data.articles.0.labor_cost', 100);
        $rate = $this->rate($process['id'], ['unit' => 'job', 'amount' => 100, 'included' => ['operator', 'machine', 'ink']]);
        $op['selection']['rate_id'] = $rate['id'];
        $article['operations'] = [$op];
        $this->request('POST', 'calculation-preview', $this->input($store, [$article]))->assertOk()->assertJsonPath('data.totals.cost', 105)->assertJsonPath('data.totals.total', 205);
    }

    public function test_decimal_conversion_loss_methods_rectangles_and_aggregated_requirements(): void
    {
        $this->startAccount();
        $store = $this->store();
        $profile = ['base_unit' => 'cm2', 'purchase_unit' => 'sheet', 'purchase_to_base_factor' => 600, 'consumption_unit' => 'cm2', 'consumption_to_base_factor' => 1, 'width_cm' => 20, 'height_cm' => 30];
        $id = $this->source($store, 'Hoja por área', 0.00000001, 'cm2', $profile);
        $a = $this->article([$this->material($id, ['amount' => 0.25, 'quantity_source' => 'purchase'])], [], ['quantity' => 2]);
        $result = $this->request('POST', 'calculation-preview', $this->input($store, [$a, $a]))->assertOk()->assertJsonPath('data.articles.0.materials.0.base_quantity', 300)
            ->assertJsonPath('data.totals.cost', 0.000006)->assertJsonPath('data.materials.0.required', 600);
        foreach (['additional' => 10, 'yield' => 0.8] as $method => $value) {
            $a['materials'] = [$this->material($id, ['method' => 'area', 'width' => 80, 'height' => 60, 'measure_unit' => 'mm', 'applications' => 2, 'loss' => ['method' => $method, 'value' => $value]])];
            $this->request('POST', 'calculation-preview', $this->input($store, [$a]))->assertOk()->assertJsonPath('data.articles.0.materials.0.base_quantity', $method === 'yield' ? 240 : 211.2);
        }
        $length = $this->source($store, 'Cinta', 10, 'm');
        $this->request('POST', 'calculation-preview', $this->input($store, [$this->article([$this->material($length, ['method' => 'length', 'amount' => 25, 'measure_unit' => 'cm'])])]))
            ->assertOk()->assertJsonPath('data.totals.cost', 2.5);
    }

    public function test_discount_tax_currencies_and_snapshot_do_not_change_consumption_or_history(): void
    {
        $this->startAccount();
        $store = $this->store();
        $id = $this->source($store, 'Vaso', 100);
        Setting::create(['type' => 'global', 'key' => 'usd_exchange_rate', 'value' => '40']);
        $input = $this->input($store, [$this->article([$this->material($id)])], ['discount' => ['method' => 'percent', 'value' => 10], 'tax_amount' => 15]);
        $result = $this->request('POST', 'calculation-preview', $input)->assertOk()->assertJsonPath('data.totals.total', 132)->assertJsonPath('data.totals.final_profit', 17)->json('data');
        $this->request('POST', 'calculation-preview', [...$input, 'discount' => ['method' => 'amount', 'value' => 0]])->assertOk()->assertJsonPath('data.materials.0.required', 1);
        $this->request('POST', 'calculation-preview', [...$input, 'currency' => 'USD', 'tax_amount' => 0])->assertOk()->assertJsonPath('data.totals.subtotal', 3.25)
            ->assertJsonPath('data.totals.total', 2.92)->assertJsonPath('data.commercial.usd_exchange_rate', 40);
        DB::table('products')->update(['cost' => 200]);
        $this->assertSame(100, $result['snapshot']['articles'][0]['materials'][0]['base_unit_cost_nio']);
        $this->request('POST', 'calculation-preview', $input)->assertOk()->assertJsonPath('data.totals.cost', 200);
        Setting::create(['type' => 'global', 'key' => 'accepted_currencies', 'value' => '["NIO"]']);
        $this->request('POST', 'calculation-preview', [...$input, 'currency' => 'USD'])->assertUnprocessable()->assertJsonValidationErrors('currency');
    }

    public function test_foreign_and_unassigned_store_inventory_resources_and_cost_permissions_are_rejected(): void
    {
        [$userA, $orgA, $roleA] = $this->startAccount();
        $storeA = $this->store();
        $idA = $this->source($storeA, 'Ajeno', 5);
        [$userB, $orgB, $roleB] = $this->startAccount();
        $storeB = $this->store();
        $this->request('POST', 'calculation-preview', $this->input($storeB, [$this->article([$this->material($idA)])]))->assertNotFound();
        $this->request('POST', 'calculation-preview', $this->input($storeA, [$this->article([])]))->assertNotFound();
        $this->request('GET', 'calculation-materials?store_id='.$storeB->id.'&organization_id='.$orgA->id)->assertOk()->assertJsonCount(0, 'data');
        $orgB->update(['owner_id' => $userA->id]);
        $this->request('POST', 'calculation-preview', $this->input($storeB, [$this->article([])]))->assertForbidden();
        $userB->stores()->attach($storeB->id, ['id' => (string) Str::uuid(), 'status' => 'active']);
        $this->request('POST', 'calculation-preview', $this->input($storeB, [$this->article([])]))->assertOk();
        $roleB->revokePermissionTo('custom_job.costs');
        $this->request('GET', 'calculation-materials?store_id='.$storeB->id)->assertForbidden();
        $this->request('POST', 'calculation-preview', $this->input($storeB, [$this->article([])]))->assertForbidden();
    }

    public function test_invalid_losses_dimensions_units_sales_only_and_discount_are_rejected(): void
    {
        $this->startAccount();
        $store = $this->store();
        $id = $this->source($store, 'Solo venta', 5, 'unit', null, 'sale');
        $this->request('GET', 'calculation-materials?store_id='.$store->id)->assertOk()->assertJsonCount(0, 'data');
        $this->request('POST', 'calculation-preview', $this->input($store, [$this->article([$this->material($id)])]))->assertUnprocessable();
        $id = $this->source($store, 'Insumo', 5);
        foreach ([['method' => 'yield', 'value' => 0], ['method' => 'yield', 'value' => 1.1], ['method' => 'none', 'value' => 20], ['method' => 'additional', 'value' => 5, 'yield' => 0.9]] as $loss) {
            $this->request('POST', 'calculation-preview', $this->input($store, [$this->article([$this->material($id, ['loss' => $loss])])]))->assertUnprocessable();
        }
        $this->request('POST', 'calculation-preview', $this->input($store, [$this->article([$this->material($id, ['method' => 'area'])])]))->assertUnprocessable();
        $this->request('POST', 'calculation-preview', $this->input($store, [$this->article([$this->material($id, ['width' => -1])])]))->assertUnprocessable();
        $this->request('POST', 'calculation-preview', $this->input($store, [$this->article([$this->material($id)])], ['discount' => ['method' => 'amount', 'value' => 100]]))->assertUnprocessable();
        $this->request('POST', 'calculation-preview', $this->input($store, [$this->article([$this->material($id)])], ['discount' => ['method' => 'percent', 'value' => 101]]))->assertUnprocessable();
    }

    public function test_rounding_is_at_line_boundary_and_discount_remainders_and_negative_profit_are_consistent(): void
    {
        $this->startAccount();
        $store = $this->store();
        $id = $this->source($store, 'Costo pequeño', 0.0025);
        $article = $this->article([$this->material($id), $this->material($id)], [], ['profit' => ['method' => 'amount', 'value' => 0, 'scope' => 'batch']]);
        $result = $this->request('POST', 'calculation-preview', $this->input($store, [$article, $article, $article], ['discount' => ['method' => 'amount', 'value' => 0.01]]))
            ->assertOk()->assertJsonPath('data.totals.cost', 0.015)->assertJsonPath('data.totals.subtotal', 0.03)->assertJsonPath('data.totals.total', 0.02)->json('data');
        $this->assertEquals(0.01, array_sum(array_column($result['articles'], 'discount')));
        $this->assertEquals(0.02, array_sum(array_column($result['articles'], 'net_price')));
        $id = $this->source($store, 'Costo', 100);
        $article = $this->article([$this->material($id)]);
        $this->request('POST', 'calculation-preview', $this->input($store, [$article], ['discount' => ['method' => 'amount', 'value' => 100]]))
            ->assertOk()->assertJsonPath('data.totals.final_profit', -70)->assertJsonPath('data.materials.0.required', 1);
        $article['quantity'] = 2;
        $article['profit'] = ['method' => 'amount', 'value' => 10, 'scope' => 'unit'];
        $this->request('POST', 'calculation-preview', $this->input($store, [$article]))->assertOk()->assertJsonPath('data.totals.total', 220);
    }

    public function test_templates_copy_compositions_reprice_and_archive_without_changing_copies(): void
    {
        $this->startAccount();
        $store = $this->store();
        $mug = $this->source($store, 'Taza', 80);
        $uv = $this->source($store, 'UV DTF', 0.1, 'cm2');
        $paper = $this->source($store, 'Opalina', 1);
        $machine = $this->machine(['minute_rate' => 2.5, 'maintenance_per_minute' => 0, 'energy_per_minute' => 0]);
        $input = $this->input($store, [$this->article([$this->material($mug), $this->material($uv, ['method' => 'area']), $this->material($paper)], [$this->operation($machine['id'])])]);
        $composition = $input;
        unset($composition['store_id']);
        $stock = DB::table('inventory_details')->get()->toArray();
        $template = $this->request('POST', 'templates', ['name' => 'Taza con tarjeta', 'description' => 'UV DTF y opalina', 'store_id' => $store->id, 'composition' => $composition])
            ->assertCreated()->assertJsonPath('data.schema_version', 1)->json('data');
        $this->assertArrayNotHasKey('store_id', $template['composition']);
        $this->request('GET', 'templates')->assertOk()->assertJsonCount(1, 'data');
        $copy = $this->request('POST', 'templates/'.$template['id'].'/instantiate', ['store_id' => $store->id])
            ->assertOk()->assertJsonPath('data.calculation.totals.total', 140.79)->assertJsonPath('data.warnings', [])->json('data.input');
        Product::find(InventoryDetail::find($mug)->product_id)->update(['cost' => 100]);
        $this->request('POST', 'templates/'.$template['id'].'/instantiate', ['store_id' => $store->id])
            ->assertOk()->assertJsonPath('data.calculation.totals.total', 166.79);
        $copy['articles'][0]['quantity'] = 2;
        $copy['articles'][0]['materials'][1]['width'] = 10;
        $this->request('POST', 'calculation-preview', $copy)->assertOk()->assertJsonPath('data.articles.0.materials.1.base_quantity', 120);
        $this->request('PUT', 'templates/'.$template['id'], ['name' => 'Versión nueva', 'store_id' => $store->id, 'composition' => $composition])->assertOk();
        $this->request('POST', 'templates/'.$template['id'].'/archive')->assertOk();
        $this->request('POST', 'templates/'.$template['id'].'/instantiate', ['store_id' => $store->id])->assertStatus(422);
        $this->request('GET', 'templates')->assertOk()->assertJsonCount(0, 'data');
        $this->request('GET', 'templates?status=all')->assertOk()->assertJsonCount(1, 'data');
        $this->assertSame(2, $copy['articles'][0]['quantity']);
        $this->assertSame('Trabajo', $copy['articles'][0]['name']);
        $this->assertEquals($stock, DB::table('inventory_details')->get()->toArray());
    }

    public function test_templates_report_all_unavailable_sources_and_reject_invalid_saves(): void
    {
        $this->startAccount();
        $store = $this->store();
        $material = $this->source($store, 'Vinil', 0.1, 'cm2');
        $machine = $this->machine();
        $rate = $this->rate($this->process()['id']);
        $input = $this->input($store, [$this->article([$this->material($material, ['method' => 'area'])], [
            $this->operation($machine['id']),
            ['selection' => [...$this->selection(['rate_id' => $rate['id']]), 'people' => []], 'machine_setup_minutes' => 0],
        ])]);
        $composition = $input;
        unset($composition['store_id']);
        $payload = ['name' => 'Vinil', 'store_id' => $store->id, 'composition' => $composition];
        $template = $this->request('POST', 'templates', $payload)->assertCreated()->json('data');
        Product::find(InventoryDetail::find($material)->product_id)->update(['product_usage' => 'sale']);
        $this->request('POST', 'resources/machines/'.$machine['id'].'/archive')->assertOk();
        $this->request('POST', 'resources/rates/'.$rate['id'].'/archive')->assertOk();
        $response = $this->request('POST', 'templates/'.$template['id'].'/instantiate', ['store_id' => $store->id])->assertOk()
            ->assertJsonPath('data.calculation', null)->assertJsonCount(3, 'data.warnings')->json('data');
        $this->assertSame($material, $response['input']['articles'][0]['materials'][0]['inventory_detail_id']);
        $this->request('PUT', 'templates/'.$template['id'], $payload)->assertStatus(422);
        $this->request('POST', 'templates', [...$payload, 'composition' => [...$composition, 'payments' => []]])->assertStatus(422);
        $this->assertDatabaseCount('custom_job_templates', 1);
        $this->request('POST', 'templates/'.$template['id'].'/archive')->assertOk();
    }

    public function test_templates_isolate_accounts_and_require_configure_and_admin_access(): void
    {
        [$user, $org, $role] = $this->startAccount();
        $store = $this->store();
        $input = $this->input($store, [$this->article([], [])]);
        unset($input['store_id']);
        $payload = ['name' => 'Digital', 'store_id' => $store->id, 'composition' => $input];
        $template = $this->request('POST', 'templates', $payload)->assertCreated()->json('data');
        $role->revokePermissionTo('custom_job.configure');
        $this->request('POST', 'templates', $payload)->assertForbidden();
        $this->request('POST', 'templates/'.$template['id'].'/archive')->assertForbidden();
        $this->request('POST', 'templates/'.$template['id'].'/instantiate', ['store_id' => $store->id])->assertOk();
        $this->authenticate($user, ['*']);
        $this->request('GET', 'templates')->assertForbidden();
        $this->startAccount();
        $this->request('GET', 'templates')->assertOk()->assertJsonCount(0, 'data');
        $this->request('PUT', 'templates/'.$template['id'], $payload)->assertNotFound();
        $this->request('POST', 'templates/'.$template['id'].'/instantiate', ['store_id' => $store->id])->assertNotFound();
        $this->request('POST', 'templates/'.$template['id'].'/archive')->assertNotFound();
    }

    public function test_template_batch_setup_and_fixed_profit_remain_once_per_batch(): void
    {
        $this->startAccount();
        $store = $this->store();
        $machine = $this->machine(['minute_rate' => 1, 'maintenance_per_minute' => 0, 'energy_per_minute' => 0]);
        $op = $this->operation($machine['id'], 0, 60, 1);
        $op['machine_setup_minutes'] = 10;
        $input = $this->input($store, [$this->article([], [$op], ['quantity' => 100, 'profit' => ['method' => 'amount', 'value' => 50, 'scope' => 'batch']])]);
        unset($input['store_id']);
        $template = $this->request('POST', 'templates', ['name' => 'Lote', 'store_id' => $store->id, 'composition' => $input])->assertCreated()->json('data');
        $this->request('POST', 'templates/'.$template['id'].'/instantiate', ['store_id' => $store->id])->assertOk()
            ->assertJsonPath('data.calculation.totals.cost', 110)->assertJsonPath('data.calculation.totals.total', 160);
        $duplicate = $this->request('POST', 'templates', ['name' => 'Lote copia', 'store_id' => $store->id, 'composition' => $template['composition']])->assertCreated()->json('data');
        $this->assertNotSame($template['id'], $duplicate['id']);
    }

    private function quotePayload(Store $store, array $articles): array
    {
        return ['request_id' => (string) Str::uuid(), 'client_name' => 'Cliente de prueba', 'note' => 'Personalización', 'input' => $this->input($store, $articles)];
    }

    private function quotesAccount(): array
    {
        [$user, $org, $role] = $this->startAccount(['custom_job.index', 'custom_job.costs', 'custom_job.configure', 'custom_job.quote']);
        foreach (['invoices', 'clients'] as $slug) {
            $module = Module::firstOrCreate(['slug' => $slug], ['name' => $slug, 'status' => 'active']);
            $org->modules()->attach($module->id, ['status' => 'active']);
        }
        foreach (['invoice.store', 'invoice.show', 'invoice.index', 'invoice.delete'] as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web'], ['display_name' => $permission]);
            $role->givePermissionTo($permission);
        }

        return [$user, $org, $role];
    }

    public function test_quotes_keep_immutable_revisions_and_issue_one_public_proforma_without_stock_changes(): void
    {
        $this->quotesAccount();
        $store = $this->store();
        $material = $this->source($store, 'Taza', 80);
        $stock = DB::table('inventory_details')->get()->toArray();
        $payload = $this->quotePayload($store, [$this->article([$this->material($material)])]);
        $quote = $this->request('POST', 'quotes', $payload)->assertCreated()->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.revisions.0.snapshot.totals.total', 104)->json('data');
        $this->request('POST', 'quotes', $payload)->assertCreated()->assertJsonPath('data.id', $quote['id']);
        $this->assertDatabaseCount('custom_job_quotes', 1);
        $this->request('POST', 'quotes', [...$payload, 'client_name' => 'Otro'])->assertStatus(409);
        $this->request('POST', 'quotes', [...$payload, 'request_id' => (string) Str::uuid(), 'grand_total' => 1])->assertStatus(422);
        $this->request('POST', 'quotes', [...$payload, 'request_id' => (string) Str::uuid(), 'input' => [...$payload['input'], 'total' => 1]])->assertStatus(422);
        $issue = ['version' => $quote['version'], 'revision_id' => $quote['revisions'][0]['id']];
        $issued = $this->request('POST', 'quotes/'.$quote['id'].'/issue', $issue)->assertOk()->assertJsonPath('data.status', 'issued')->json('data');
        $this->request('POST', 'quotes/'.$quote['id'].'/issue', $issue)->assertOk()->assertJsonPath('data.proforma.id', $issued['proforma']['id']);
        $this->assertDatabaseCount('invoices', 1);
        Product::find(InventoryDetail::find($material)->product_id)->update(['cost' => 100, 'name' => 'Taza actual']);
        $invoice = Invoice::find($issued['proforma']['id']);
        $public = CustomJobProforma::data($invoice);
        $this->assertSame(104, $public['grand_total']);
        $this->assertArrayNotHasKey('cost', $public['invoice_details'][0]);
        $this->assertNull($public['invoice_details'][0]['product_id']);
        $resource = (new InvoiceResource($invoice))->resolve(request());
        $this->assertSame($public, $resource);
        $this->assertArrayNotHasKey('snapshot', $resource);
        $this->assertSame(0, (int) $invoice->paid_in_nio);
        $this->assertSame(0, (int) $invoice->paid_in_usd);
        $this->assertNull($invoice->cash_session_id);
        $this->assertSame('PROFORMA', $invoice->payment_method);
        $this->request('GET', 'quotes/'.$quote['id'])->assertOk()->assertJsonPath('data.revisions.0.snapshot.articles.0.materials.0.name', 'Taza');
        $update = $payload;
        unset($update['request_id']);
        $update['version'] = $issued['version'];
        $edited = $this->request('PUT', 'quotes/'.$quote['id'], $update)->assertOk()->assertJsonPath('data.status', 'draft')
            ->assertJsonCount(2, 'data.revisions')->assertJsonPath('data.revisions.0.snapshot.totals.total', 130)->json('data');
        $this->request('PUT', 'quotes/'.$quote['id'], $update)->assertStatus(409);
        $this->request('POST', 'quotes/'.$quote['id'].'/issue', $issue)->assertStatus(409);
        $this->request('POST', 'quotes/'.$quote['id'].'/issue', ['version' => $edited['version'], 'revision_id' => $edited['revisions'][0]['id']])->assertOk()
            ->assertJsonPath('data.proforma.id', $issued['proforma']['id']);
        $this->assertDatabaseCount('invoices', 1);
        $this->assertSame(104, $edited['revisions'][1]['commercial']['totals']['total']);
        $this->assertEquals($stock, DB::table('inventory_details')->get()->toArray());
        $this->json('DELETE', '/api/v1/invoices/'.$invoice->id)->assertStatus(409);
    }

    public function test_quote_readers_without_cost_permission_receive_only_public_commercial_history(): void
    {
        [$user, $org, $role] = $this->quotesAccount();
        $store = $this->store();
        $quote = $this->request('POST', 'quotes', $this->quotePayload($store, [$this->article([], [$this->operation($this->machine()['id'])])]))->assertCreated()->json('data');
        $role->revokePermissionTo('custom_job.costs');
        $this->request('GET', 'quotes/'.$quote['id'])->assertOk()->assertJsonMissingPath('data.revisions.0.snapshot')
            ->assertJsonPath('data.revisions.0.commercial.articles.0.name', 'Trabajo');
        $this->request('GET', 'quotes?store_id='.$store->id)->assertOk()->assertJsonMissingPath('data.data.0.revisions.0.snapshot');
        $this->request('POST', 'quotes', $this->quotePayload($store, [$this->article([])]))->assertForbidden();
        $this->request('POST', 'resources/processes', ['name' => 'No permitido'])->assertForbidden();
        $this->request('POST', 'templates', [])->assertForbidden();
    }

    public function test_duplicate_quotes_recalculate_current_rates_and_remain_drafts_with_independent_revisions(): void
    {
        $this->quotesAccount();
        $store = $this->store();
        $rate = $this->rate($this->process()['id'], ['amount' => 10, 'preparation_cost' => 5, 'unit' => 'job']);
        $article = $this->article([], [['selection' => $this->selection(['rate_id' => $rate['id'], 'people' => []]), 'machine_setup_minutes' => 0]], ['quantity' => 100]);
        $quote = $this->request('POST', 'quotes', $this->quotePayload($store, [$article]))->assertCreated()->json('data');
        CustomJobRate::find($rate['id'])->update(['configuration' => [...$rate['configuration'], 'amount' => 20]]);
        $request = ['request_id' => (string) Str::uuid(), 'revision_id' => $quote['revisions'][0]['id']];
        $copy = $this->request('POST', 'quotes/'.$quote['id'].'/duplicate', $request)->assertCreated()->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.revisions.0.snapshot.totals.total', 32.5)->assertJsonPath('data.proforma', null)->json('data');
        $this->request('POST', 'quotes/'.$quote['id'].'/duplicate', $request)->assertCreated()->assertJsonPath('data.id', $copy['id']);
        $this->assertNotSame($quote['id'], $copy['id']);
        $this->assertDatabaseCount('custom_job_quotes', 2);
        $this->assertDatabaseCount('invoices', 0);
        $this->request('GET', 'quotes/'.$quote['id'])->assertOk()->assertJsonPath('data.revisions.0.snapshot.totals.total', 19.5);
    }

    public function test_quotes_reject_foreign_clients_accounts_unassigned_stores_and_missing_permissions(): void
    {
        [$user, $org, $role] = $this->quotesAccount();
        $store = $this->store();
        $client = Client::create(['name' => 'Cliente', 'status' => 'active']);
        $payload = [...$this->quotePayload($store, [$this->article([])]), 'client_id' => $client->id];
        $quote = $this->request('POST', 'quotes', $payload)->assertCreated()->assertJsonPath('data.client_name', 'Cliente')->json('data');
        $role->revokePermissionTo('custom_job.quote');
        $this->request('POST', 'quotes', $this->quotePayload($store, [$this->article([])]))->assertForbidden();
        $role->givePermissionTo('custom_job.quote');
        $role->revokePermissionTo('invoice.store');
        $this->request('POST', 'quotes/'.$quote['id'].'/issue', ['version' => 1, 'revision_id' => $quote['revisions'][0]['id']])->assertForbidden();
        $this->quotesAccount();
        $foreignStore = $this->store();
        $this->request('GET', 'quotes/'.$quote['id'])->assertNotFound();
        $this->request('POST', 'quotes', [...$payload, 'request_id' => (string) Str::uuid(), 'input' => $this->input($foreignStore, [$this->article([])])])->assertNotFound();
        $this->authenticate($user);
        TenantManager::setTenant($org);
        $org->owner_id = (string) Str::uuid();
        $org->save();
        $this->request('GET', 'quotes/'.$quote['id'])->assertForbidden();
        $this->request('GET', 'quotes?store_id='.$store->id)->assertForbidden();
    }

    private function issuedOrderQuote(int $quantity = 100): array
    {
        [$user,$org,$role] = $this->quotesAccount();
        $role->givePermissionTo('custom_job.order');
        $store = $this->store();
        $material = $this->source($store, 'Vaso', 5);
        $quote = $this->request('POST', 'quotes', $this->quotePayload($store, [$this->article([$this->material($material)], [], ['quantity' => $quantity])]))->assertCreated()->json('data');
        $quote = $this->request('POST', 'quotes/'.$quote['id'].'/issue', ['version' => $quote['version'], 'revision_id' => $quote['revisions'][0]['id']])->assertOk()->json('data');
        $data = ['version' => $quote['version'], 'revision_id' => $quote['issued_revision_id'], 'delivery_local' => '2026-10-20T23:30'];

        return [$user, $org, $role, $store, $quote, $data, $material];
    }

    public function test_order_conversion_copies_exact_revision_is_idempotent_and_does_not_start_operations(): void
    {
        [$user,$org,$role,$store,$quote,$data,$material] = $this->issuedOrderQuote();
        $stock = DB::table('inventory_details')->get()->toArray();
        Product::find(InventoryDetail::find($material)->product_id)->update(['name' => 'Vaso cambiado', 'cost' => 99]);
        $order = $this->request('POST', 'quotes/'.$quote['id'].'/convert', $data)->assertCreated()
            ->assertJsonPath('data.number', 'PED-000001')->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.delivery_timezone', 'America/Managua')->assertJsonPath('data.delivery_at', '2026-10-21T05:30:00Z')
            ->assertJsonPath('data.items.0.quantity', 100)->assertJsonPath('data.items.0.name', 'Trabajo')
            ->assertJsonPath('data.commercial.totals.total', 650)->assertJsonPath('data.snapshot.articles.0.materials.0.name', 'Vaso')
            ->assertJsonPath('data.confirmation.available', true)->assertJsonPath('data.operations.payment.pending', 650)->json('data');
        $this->request('POST', 'quotes/'.$quote['id'].'/convert', $data)->assertCreated()->assertJsonPath('data.id', $order['id']);
        $this->assertDatabaseCount('custom_job_orders', 1);
        $this->assertDatabaseCount('custom_job_order_items', 1);
        $this->assertDatabaseCount('invoices', 1); // Only the existing proforma, no invoice created on conversion.
        $this->assertEquals($stock, DB::table('inventory_details')->get()->toArray());
        $this->request('GET', 'quotes/'.$quote['id'])->assertOk()->assertJsonPath('data.status', 'converted')->assertJsonPath('data.conversion.order_id', $order['id']);
        $this->request('POST', 'quotes/'.$quote['id'].'/convert', [...$data, 'delivery_local' => '2026-10-21T08:00'])->assertStatus(409);
        $this->request('POST', 'orders/'.$order['id'].'/confirm', ['version' => 1])->assertOk();
        $this->assertSame('confirmed', CustomJobOrder::find($order['id'])->status);
        $this->request('POST', 'quotes/'.$quote['id'].'/issue', ['version' => 3, 'revision_id' => $quote['issued_revision_id']])->assertStatus(409);
    }

    public function test_orders_require_current_issued_revision_and_reject_forged_payloads(): void
    {
        [$user,$org,$role,$store,$quote,$data] = $this->issuedOrderQuote();
        $this->request('POST', 'quotes/'.$quote['id'].'/convert', [...$data, 'version' => 1])->assertStatus(409);
        $this->request('POST', 'quotes/'.$quote['id'].'/convert', [...$data, 'revision_id' => (string) Str::uuid()])->assertStatus(409);
        $this->request('POST', 'quotes/'.$quote['id'].'/convert', [...$data, 'total' => 1])->assertStatus(422);
        $this->request('POST', 'quotes/'.$quote['id'].'/convert', [...$data, 'delivery_local' => '2026-02-30T08:00'])->assertStatus(422);
        $this->request('POST', 'quotes/'.$quote['id'].'/convert', [...$data, 'delivery_local' => ''])->assertStatus(422);
        $payload = $this->quotePayload($store, [$this->article([])]);
        $draft = $this->request('POST', 'quotes', $payload)->assertCreated()->json('data');
        $this->request('POST', 'quotes/'.$draft['id'].'/convert', ['version' => 1, 'revision_id' => $draft['revisions'][0]['id'], 'delivery_local' => $data['delivery_local']])->assertStatus(409);
        $this->request('POST', 'quotes/'.$quote['id'].'/convert', [...$data, 'delivery_local' => '0001-01-01T08:00'])->assertStatus(422);
        $this->assertDatabaseCount('custom_job_orders', 0);
        $store->custom_job_timezone = 'Bad/Zone';
        $store->save();
        $this->request('POST', 'quotes/'.$quote['id'].'/convert', $data)->assertStatus(422);
    }

    public function test_order_commitments_keep_timezone_and_filter_by_local_day_with_version_conflicts(): void
    {
        [$user,$org,$role,$store,$quote,$data] = $this->issuedOrderQuote();
        $order = $this->request('POST', 'quotes/'.$quote['id'].'/convert', $data)->assertCreated()->json('data');
        $this->request('GET', 'orders?store_id='.$store->id.'&delivery_from=2026-10-20&delivery_to=2026-10-20')->assertOk()->assertJsonCount(1, 'data');
        $this->request('GET', 'orders?store_id='.$store->id.'&delivery_from=2026-10-21')->assertOk()->assertJsonCount(0, 'data');
        $this->request('GET', 'orders?store_id='.$store->id.'&delivery_to=2026-01-01')->assertOk()->assertJsonCount(0, 'data');
        $store->custom_job_timezone = 'UTC';
        $store->save();
        $this->request('PATCH', 'orders/'.$order['id'].'/commitment', ['version' => 1, 'delivery_local' => '2026-10-22T08:45'])->assertOk()
            ->assertJsonPath('data.version', 2)->assertJsonPath('data.delivery_at', '2026-10-22T14:45:00Z')
            ->assertJsonPath('data.delivery_timezone', 'America/Managua');
        $this->request('PATCH', 'orders/'.$order['id'].'/commitment', ['version' => 1, 'delivery_local' => '2026-10-24T09:00'])->assertStatus(409);
        $this->assertSame('2026-10-22T08:45', CustomJobOrder::find($order['id'])->delivery_local);
        $this->request('GET', 'orders?store_id='.$store->id.'&delivery_to=2026-10-22')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_order_cost_redaction_permissions_tenant_and_store_isolation(): void
    {
        [$user,$org,$role,$store,$quote,$data] = $this->issuedOrderQuote();
        $role->revokePermissionTo('custom_job.costs');
        $order = $this->request('POST', 'quotes/'.$quote['id'].'/convert', $data)->assertCreated()->assertJsonMissingPath('data.snapshot')->assertJsonMissingPath('data.items.0.composition')->json('data');
        $this->request('GET', 'orders/'.$order['id'])->assertOk()->assertJsonMissingPath('data.snapshot');
        $this->request('GET', 'orders?store_id='.$store->id)->assertOk()->assertJsonMissingPath('data.0.snapshot')->assertJsonMissingPath('data.0.items.0.composition');
        $role->revokePermissionTo('custom_job.order');
        $this->request('POST', 'quotes/'.$quote['id'].'/convert', $data)->assertForbidden();
        $this->request('PATCH', 'orders/'.$order['id'].'/commitment', ['version' => 1, 'delivery_local' => '2026-10-21T10:00'])->assertForbidden();
        $this->request('POST', 'orders/'.$order['id'].'/confirm', ['version' => 1])->assertForbidden();
        [, , $foreignRole] = $this->quotesAccount();
        $foreignRole->givePermissionTo('custom_job.order');
        $this->request('GET', 'orders/'.$order['id'])->assertNotFound();
        $this->request('POST', 'quotes/'.$quote['id'].'/convert', $data)->assertNotFound();
        $this->authenticate($user);
        TenantManager::setTenant($org);
        $org->owner_id = (string) Str::uuid();
        $org->save();
        $this->request('GET', 'orders/'.$order['id'])->assertForbidden();
        $this->request('GET', 'orders?store_id='.$store->id)->assertForbidden();
    }

    public function test_confirmation_reservation_failure_rolls_back_and_success_retries_once(): void
    {
        [$user,$org,$role,$store,$quote,$data] = $this->issuedOrderQuote();
        $order = $this->request('POST', 'quotes/'.$quote['id'].'/convert', $data)->assertCreated()->json('data');
        $service = app(CustomJobOrders::class);
        $adapter = new class implements CustomJobReservation
        {
            public bool $fail = true;

            public int $calls = 0;

            public function reserve(User $user, CustomJobOrder $order, array $selection = []): void
            {
                $this->calls++;
                DB::table('stores')->where('id', $order->store_id)->increment('custom_job_order_number');
                if ($this->fail) {
                    throw new \RuntimeException('Apartado fallido');
                }
            }
        };
        app()->instance(CustomJobReservation::class, $adapter);
        try {
            $service->confirm($user, $order['id'], 1);
            $this->fail('Expected reservation error');
        } catch (\RuntimeException $e) {
            $this->assertSame('Apartado fallido', $e->getMessage());
        }
        $this->assertSame('draft', CustomJobOrder::find($order['id'])->status);
        $this->assertNull(CustomJobOrder::find($order['id'])->confirmed_at);
        $this->assertSame(1, (int) $store->fresh()->custom_job_order_number);
        $adapter->fail = false;
        $service->confirm($user, $order['id'], 1);
        $service->confirm($user, $order['id'], 1);
        $this->assertSame(2, $adapter->calls);
        $this->assertSame('confirmed', CustomJobOrder::find($order['id'])->status);
        $this->assertSame(2, (int) $store->fresh()->custom_job_order_number);
        app()->forgetInstance(CustomJobReservation::class);
    }

    public function test_order_numbers_are_unique_per_store_and_dst_commitments_are_validated(): void
    {
        [$user,$org,$role,$store,$quote,$data] = $this->issuedOrderQuote();
        $store->custom_job_timezone = 'America/New_York';
        $store->save();
        foreach (['2026-03-08T02:30', '2026-11-01T01:30'] as $local) {
            $this->request('POST', 'quotes/'.$quote['id'].'/convert', [...$data, 'delivery_local' => $local])->assertStatus(422);
        }
        $first = $this->request('POST', 'quotes/'.$quote['id'].'/convert', $data)->assertCreated()->json('data');
        $draft = $this->request('POST', 'quotes', $this->quotePayload($store, [$this->article([])]))->assertCreated()->json('data');
        $issued = $this->request('POST', 'quotes/'.$draft['id'].'/issue', ['version' => 1, 'revision_id' => $draft['revisions'][0]['id']])->assertOk()->json('data');
        $this->request('POST', 'quotes/'.$draft['id'].'/convert', ['version' => $issued['version'], 'revision_id' => $issued['issued_revision_id'], 'delivery_local' => $data['delivery_local']])->assertCreated()->assertJsonPath('data.number', 'PED-000002');
        $this->assertDatabaseCount('custom_job_orders', 2);
        $this->assertSame('2026-10-21T03:30:00Z', $first['delivery_at']);
    }

    private function materialOrder(): array
    {
        [$user,$org,$role] = $this->quotesAccount();
        $role->givePermissionTo(['custom_job.order', 'custom_job.return']);
        $store = $this->store();
        $cup = $this->source($store, 'Vaso', 5);
        $vinyl = $this->source($store, 'Vinil', 0.01, 'cm2');
        DB::table('inventory_details')->where('id', $vinyl)->update(['quantity' => 10000]);
        $quote = $this->request('POST', 'quotes', $this->quotePayload($store, [$this->article([$this->material($cup), $this->material($vinyl, ['method' => 'area'])], [], ['quantity' => 100])]))->assertCreated()->json('data');
        $quote = $this->request('POST', 'quotes/'.$quote['id'].'/issue', ['version' => 1, 'revision_id' => $quote['revisions'][0]['id']])->assertOk()->json('data');
        $order = $this->request('POST', 'quotes/'.$quote['id'].'/convert', ['version' => $quote['version'], 'revision_id' => $quote['issued_revision_id'], 'delivery_local' => '2026-10-20T16:00'])->assertCreated()->json('data');

        return [$user, $org, $role, $store, $order, $cup, $vinyl];
    }

    public function test_material_reservation_consumption_selective_returns_recovery_and_additional_balance(): void
    {
        [$user,$org,$role,$store,$order,$cup,$vinyl] = $this->materialOrder();
        $confirmed = $this->request('POST', 'orders/'.$order['id'].'/confirm', ['version' => 1])->assertOk()->json('data');
        $this->request('POST', 'orders/'.$order['id'].'/confirm', ['version' => 1])->assertOk();
        $this->assertSame(900.0, InventoryDetail::find($cup)->quantity);
        $this->assertSame(5200.0, InventoryDetail::find($vinyl)->quantity);
        $this->assertDatabaseCount('inventory_movements', 2);
        $rows = $this->request('GET', 'orders/'.$order['id'].'/materials')->assertOk()->json('data');
        $cupRow = collect($rows['allocations'])->firstWhere('product_id', InventoryDetail::find($cup)->product_id);
        $vinylRow = collect($rows['allocations'])->firstWhere('product_id', InventoryDetail::find($vinyl)->product_id);
        $service = app(CustomJobMaterials::class);
        $consumeKey = (string) Str::uuid();
        $consume = [['allocation_id' => $cupRow['id'], 'quantity' => 40], ['allocation_id' => $vinylRow['id'], 'quantity' => 1920]];
        $service->mutate($user, $order['id'], $confirmed['version'], $consumeKey, 'consume', $consume);
        $service->mutate($user, $order['id'], $confirmed['version'], $consumeKey, 'consume', $consume);
        $this->assertSame(900.0, InventoryDetail::find($cup)->quantity);
        $this->assertDatabaseCount('inventory_movements', 2);
        $rows = $this->request('GET', 'orders/'.$order['id'].'/materials')->assertOk()->json('data');
        $cupRow = collect($rows['allocations'])->firstWhere('id', $cupRow['id']);
        $this->assertSame(60, $cupRow['remaining']);
        $version = CustomJobOrder::find($order['id'])->version;
        $key = (string) Str::uuid();
        $returns = ['version' => $version, 'request_id' => $key, 'lines' => [['allocation_id' => $cupRow['id'], 'quantity' => 50], ['allocation_id' => $vinylRow['id'], 'quantity' => 2400]]];
        $this->request('POST', 'orders/'.$order['id'].'/materials/return', $returns)->assertOk();
        $this->request('POST', 'orders/'.$order['id'].'/materials/return', $returns)->assertOk();
        $rows = $this->request('GET', 'orders/'.$order['id'].'/materials')->assertOk()->json('data');
        $this->assertSame(10, collect($rows['allocations'])->firstWhere('id', $cupRow['id'])['remaining']);
        $this->assertSame(480, collect($rows['allocations'])->firstWhere('id', $vinylRow['id'])['remaining']);
        $this->assertSame(950.0, InventoryDetail::find($cup)->quantity);
        $this->assertSame(7600.0, InventoryDetail::find($vinyl)->quantity);
        $this->request('POST', 'orders/'.$order['id'].'/materials/return', [...$returns, 'lines' => [['allocation_id' => $cupRow['id'], 'quantity' => 51]]])->assertStatus(409);
        $version = CustomJobOrder::find($order['id'])->version;
        $this->request('POST', 'orders/'.$order['id'].'/materials/return', ['version' => $version, 'request_id' => (string) Str::uuid(), 'lines' => [['allocation_id' => $cupRow['id'], 'quantity' => 11]]])->assertStatus(422);
        $used = CustomJobMaterialEvent::where('allocation_id', $cupRow['id'])->where('kind', 'consume')->first();
        $recovery = ['version' => $version, 'request_id' => (string) Str::uuid(), 'lines' => [['allocation_id' => $cupRow['id'], 'quantity' => 40, 'source_event_id' => $used->id]]];
        $this->request('POST', 'orders/'.$order['id'].'/materials/recover', $recovery)->assertOk();
        $this->request('POST', 'orders/'.$order['id'].'/materials/recover', $recovery)->assertOk();
        $this->request('POST', 'orders/'.$order['id'].'/materials/recover', [...$recovery, 'version' => CustomJobOrder::find($order['id'])->version, 'request_id' => (string) Str::uuid(), 'lines' => [['allocation_id' => $cupRow['id'], 'quantity' => 1, 'source_event_id' => $used->id]]])->assertStatus(422);
        $this->assertSame(990.0, InventoryDetail::find($cup)->quantity);
        $this->assertSame(40.0, (float) $used->fresh()->getRawOriginal('quantity'));
        $extra = ['version' => CustomJobOrder::find($order['id'])->version, 'request_id' => (string) Str::uuid(), 'selections' => [['item_id' => $order['items'][0]['id'], 'material_index' => 0, 'target' => 80, 'sources' => [['inventory_detail_id' => $cup, 'quantity' => 30]]]]];
        $this->request('POST', 'orders/'.$order['id'].'/materials/additional', $extra)->assertOk();
        $this->request('POST', 'orders/'.$order['id'].'/materials/additional', $extra)->assertOk();
        $this->assertSame(960.0, InventoryDetail::find($cup)->quantity);
        $rows = $this->request('GET', 'orders/'.$order['id'].'/materials')->assertOk()->json('data');
        $a = collect($rows['allocations'])->firstWhere('id', $cupRow['id']);
        $this->assertSame(130, $a['assigned']);
        $this->assertSame(40, $a['remaining']);
        $this->assertSame(40, $a['used']);
        $this->assertSame(40, $a['recovered']);
    }

    public function test_material_split_origins_fractional_stock_and_forbidden_product_unit_or_destination(): void
    {
        [$user,$org,$role,$store,$order,$cup,$vinyl] = $this->materialOrder();
        $otherStore = $this->store();
        $inventory = Inventory::create(['name' => 'Otra sucursal', 'store_id' => $otherStore->id, 'status' => 'active']);
        $other = InventoryDetail::create(['inventory_id' => $inventory->id, 'product_id' => InventoryDetail::find($cup)->product_id, 'quantity' => 60, 'price' => 9]);
        $selections = [['item_id' => $order['items'][0]['id'], 'material_index' => 0, 'sources' => [['inventory_detail_id' => $cup, 'quantity' => 40], ['inventory_detail_id' => $other->id, 'quantity' => 60]]], ['item_id' => $order['items'][0]['id'], 'material_index' => 1, 'sources' => [['inventory_detail_id' => $vinyl, 'quantity' => 4800]]]];
        $this->request('POST', 'orders/'.$order['id'].'/confirm', ['version' => 1, 'selections' => $selections])->assertOk();
        $this->assertSame(960.0, InventoryDetail::find($cup)->quantity);
        $this->assertSame(0.0, $other->fresh()->quantity);
        $rows = $this->request('GET', 'orders/'.$order['id'].'/materials')->assertOk()->json('data');
        $a = collect($rows['allocations'])->firstWhere('inventory_detail_id', $other->id);
        $payload = ['version' => 2, 'request_id' => (string) Str::uuid(), 'lines' => [['allocation_id' => $a['id'], 'quantity' => 0.123456, 'inventory_detail_id' => $cup]]];
        $this->request('POST', 'orders/'.$order['id'].'/materials/return', $payload)->assertForbidden();
        Permission::firstOrCreate(['name' => 'inventory.update', 'guard_name' => 'web'], ['display_name' => 'Editar inventario']);
        $role->givePermissionTo('inventory.update');
        $this->request('POST', 'orders/'.$order['id'].'/materials/return', $payload)->assertOk();
        $this->assertSame(960.123456, InventoryDetail::find($cup)->quantity);
        $payload['version'] = CustomJobOrder::find($order['id'])->version;
        $payload['request_id'] = (string) Str::uuid();
        $payload['lines'][0]['quantity'] = 0.0000001;
        $this->request('POST', 'orders/'.$order['id'].'/materials/return', $payload)->assertStatus(422);
        $vinylAllocation = collect($rows['allocations'])->firstWhere('inventory_detail_id', $vinyl);
        $payload['request_id'] = (string) Str::uuid();
        $payload['lines'] = [['allocation_id' => $vinylAllocation['id'], 'quantity' => 1, 'inventory_detail_id' => $cup],
            ['allocation_id' => $a['id'], 'quantity' => 1, 'inventory_detail_id' => $cup]];
        $this->request('POST', 'orders/'.$order['id'].'/materials/return', $payload)->assertStatus(422);
        $this->assertSame(960.123456, InventoryDetail::find($cup)->quantity);
    }

    public function test_material_shortages_follow_policy_and_atomic_failure_has_no_partial_assignment(): void
    {
        [$user,$org,$role,$store,$order,$cup,$vinyl] = $this->materialOrder();
        Setting::create(['key' => 'allow_negative_stock', 'type' => 'global', 'value' => 'false']);
        DB::table('inventory_details')->where('id', $vinyl)->update(['quantity' => 1]);
        $this->request('POST', 'orders/'.$order['id'].'/confirm', ['version' => 1])->assertStatus(422);
        $this->assertSame('draft', CustomJobOrder::find($order['id'])->status);
        $this->assertSame(1000.0, InventoryDetail::find($cup)->quantity);
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertDatabaseCount('custom_job_material_events', 0);
        Setting::where('key', 'allow_negative_stock')->update(['value' => 'true']);
        $this->request('POST', 'orders/'.$order['id'].'/confirm', ['version' => 1])->assertOk();
        $rows = $this->request('GET', 'orders/'.$order['id'].'/materials')->assertOk()->json('data');
        $a = collect($rows['allocations'])->firstWhere('inventory_detail_id', $vinyl);
        $this->assertSame(4799, $a['shortage_remaining']);
        $this->assertSame(1, $a['physical_remaining']);
        try {
            app(CustomJobMaterials::class)->mutate($user, $order['id'], 2, (string) Str::uuid(), 'consume', [['allocation_id' => $a['id'], 'quantity' => 2]]);
            $this->fail('Cannot consume virtual stock');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('físicamente', $e->getMessage());
        }
        Permission::firstOrCreate(['name' => 'inventory.update', 'guard_name' => 'web'], ['display_name' => 'Editar inventario']);
        $role->givePermissionTo('inventory.update');
        $inventory = Inventory::create(['store_id' => $store->id, 'name' => 'Destino autorizado', 'status' => 'active']);
        $other = InventoryDetail::create(['inventory_id' => $inventory->id, 'product_id' => $a['product_id'], 'quantity' => 10, 'status' => 'active']);
        $this->request('POST', 'orders/'.$order['id'].'/materials/return', ['version' => 2, 'request_id' => (string) Str::uuid(),
            'lines' => [['allocation_id' => $a['id'], 'quantity' => 2, 'inventory_detail_id' => $other->id]]])->assertStatus(422);
        $this->assertSame(10.0, $other->fresh()->quantity);
        $this->request('POST', 'orders/'.$order['id'].'/materials/return', ['version' => 2, 'request_id' => (string) Str::uuid(),
            'lines' => [['allocation_id' => $a['id'], 'quantity' => 4800]]])->assertOk();
        $this->assertSame(1.0, InventoryDetail::find($vinyl)->quantity);
    }

    public function test_two_orders_compete_for_stock_and_retries_cannot_reverse_material_movements(): void
    {
        [$user,$org,$role,$store,$order,$cup,$vinyl] = $this->materialOrder();
        Setting::create(['key' => 'allow_negative_stock', 'type' => 'global', 'value' => 'false']);
        DB::table('inventory_details')->where('id', $cup)->update(['quantity' => 100]);
        $this->request('POST', 'orders/'.$order['id'].'/confirm', ['version' => 1])->assertOk();
        $quote = $this->request('POST', 'quotes', $this->quotePayload($store, [$this->article([$this->material($cup)], [], ['quantity' => 1])]))->assertCreated()->json('data');
        $quote = $this->request('POST', 'quotes/'.$quote['id'].'/issue', ['version' => 1, 'revision_id' => $quote['revisions'][0]['id']])->assertOk()->json('data');
        $second = $this->request('POST', 'quotes/'.$quote['id'].'/convert', ['version' => $quote['version'], 'revision_id' => $quote['issued_revision_id'], 'delivery_local' => '2026-10-20T16:00'])->assertCreated()->json('data');
        $this->request('POST', 'orders/'.$second['id'].'/confirm', ['version' => 1])->assertStatus(422);
        $this->assertSame('draft', CustomJobOrder::find($second['id'])->status);
        $movement = InventoryMovement::first();
        try {
            app(InventoryMovementService::class)->reverseMovement($movement);
            $this->fail('Cannot reverse linked material movement');
        } catch (HttpException $e) {
            $this->assertSame(409, $e->getStatusCode());
        }
        $this->assertSame(0.0, InventoryDetail::find($cup)->quantity);
    }

    public function test_material_sources_and_balances_reject_invalid_classification_units_permissions_and_foreign_accounts(): void
    {
        [$user,$org,$role,$store,$order,$cup,$vinyl] = $this->materialOrder();
        Product::find(InventoryDetail::find($cup)->product_id)->update(['product_usage' => 'sale']);
        $this->request('POST', 'orders/'.$order['id'].'/confirm', ['version' => 1])->assertStatus(422);
        DB::table('products')->where('id', InventoryDetail::find($cup)->product_id)->update(['product_usage' => 'both', 'unit_of_measure' => 'cm']);
        $this->request('POST', 'orders/'.$order['id'].'/confirm', ['version' => 1])->assertStatus(422);
        DB::table('products')->where('id', InventoryDetail::find($cup)->product_id)->update(['unit_of_measure' => 'unit']);
        $this->request('POST', 'orders/'.$order['id'].'/confirm', ['version' => 1])->assertOk();
        $role->revokePermissionTo('custom_job.costs');
        $this->request('GET', 'orders/'.$order['id'].'/materials')->assertOk()->assertJsonMissingPath('data.held_value_nio')->assertJsonMissingPath('data.allocations.0.unit_cost_nio')->assertJsonMissingPath('data.requirements.0.unit_cost_nio');
        $role->revokePermissionTo('custom_job.return');
        $this->request('POST', 'orders/'.$order['id'].'/materials/return', ['version' => 2, 'request_id' => (string) Str::uuid(), 'lines' => []])->assertForbidden();
        $this->quotesAccount();
        $this->request('GET', 'orders/'.$order['id'].'/materials')->assertNotFound();
    }
    private function productionOrder(): array
    {
        $fixture=$this->materialOrder();
        $fixture[2]->givePermissionTo('custom_job.production');
        $id=$fixture[4]['id'];
        $this->request('POST','orders/'.$id.'/confirm',['version'=>1])->assertOk();
        $this->request('POST','orders/'.$id.'/production',['version'=>2])->assertOk();
        return $fixture;
    }

    private function progressPayload(array $order,int $version,float $produced,float $discarded=0,array $consumption=[]): array
    {
        return ['version'=>$version,'request_id'=>(string)Str::uuid(),'item_id'=>$order['items'][0]['id'],'produced'=>$produced,'discarded'=>$discarded,
            'occurred_local'=>'2026-10-01T16:00','note'=>'Prueba progreso','consumption'=>$consumption];
    }

    public function test_production_daily_increments_consumption_and_generation_are_idempotent(): void
    {
        [$user,$org,$role,$store,$order,$cup,$vinyl]=$this->productionOrder(); $id=$order['id'];
        $this->request('POST','orders/'.$id.'/production',['version'=>2])->assertOk();
        $this->assertDatabaseCount('custom_job_work_orders',1);
        $this->assertSame(3,CustomJobOrder::find($id)->version);
        $alloc=$this->request('GET','orders/'.$id.'/materials')->assertOk()->json('data.allocations');
        $cupA=collect($alloc)->firstWhere('inventory_detail_id',$cup);
        $vinylA=collect($alloc)->firstWhere('inventory_detail_id',$vinyl);
        foreach([20,15,5] as $day=>$quantity) {
            $p=$this->progressPayload($order,3+$day,$quantity,0,[['allocation_id'=>$cupA['id'],'quantity'=>$quantity],['allocation_id'=>$vinylA['id'],'quantity'=>$quantity*48]]);
            $p['occurred_local']='2026-10-0'.($day+1).'T16:00';
            $this->request('POST','orders/'.$id.'/production/events',$p)->assertOk();
            $this->request('POST','orders/'.$id.'/production/events',$p)->assertOk();
        }
        $result=$this->request('GET','orders/'.$id.'/production')->assertOk()->json('data');
        $this->assertSame(40,$result['items'][0]['produced']); $this->assertSame(60,$result['items'][0]['pending']);
        $this->assertSame('in_progress',$result['status']); $this->assertCount(3,$result['events']);
        $this->assertSame(48,$result['items'][0]['materials'][1]['per_piece']);
        $this->assertSame(900.0,InventoryDetail::find($cup)->quantity); $this->assertSame(5200.0,InventoryDetail::find($vinyl)->quantity);
        $this->assertDatabaseCount('inventory_movements',2);
        $ledger=$this->request('GET','orders/'.$id.'/materials')->assertOk()->json('data.allocations');
        $this->assertSame(60,collect($ledger)->firstWhere('inventory_detail_id',$cup)['remaining']);
        $p['produced']=6; $this->request('POST','orders/'.$id.'/production/events',$p)->assertStatus(409);
    }

    public function test_production_preparation_discards_and_corrections_preserve_material_consumption(): void
    {
        [$user,$org,$role,$store,$order,$cup,$vinyl]=$this->productionOrder(); $id=$order['id'];
        $a=collect($this->request('GET','orders/'.$id.'/materials')->json('data.allocations'))->firstWhere('inventory_detail_id',$cup);
        $prepare=$this->progressPayload($order,3,0,0,[['allocation_id'=>$a['id'],'quantity'=>12]]);
        $this->request('POST','orders/'.$id.'/production/events',$prepare)->assertOk()->assertJsonPath('production.items.0.produced',0);
        $p=$this->progressPayload($order,4,10,2);
        $result=$this->request('POST','orders/'.$id.'/production/events',$p)->assertOk()->json('production');
        $original=collect($result['events'])->firstWhere('produced',10);
        $correction=[...$this->progressPayload($order,5,0),'corrects_event_id'=>$original['id'],'note'=>'Conteo incorrecto'];
        $this->request('POST','orders/'.$id.'/production/events',$correction)->assertOk()->assertJsonPath('production.items.0.produced',0)->assertJsonPath('production.items.0.discarded',0);
        $this->request('POST','orders/'.$id.'/production/events',$correction)->assertOk();
        $correction['version']=6; $correction['request_id']=(string)Str::uuid();
        $this->request('POST','orders/'.$id.'/production/events',$correction)->assertStatus(409);
        $ledger=$this->request('GET','orders/'.$id.'/materials')->json('data.allocations');
        $this->assertSame(12,collect($ledger)->firstWhere('id',$a['id'])['used']);
        $this->assertSame(900.0,InventoryDetail::find($cup)->quantity);
        $this->assertDatabaseCount('custom_job_production_events',3);
        $finish=$this->progressPayload($order,6,100);
        $this->request('POST','orders/'.$id.'/production/events',$finish)->assertOk()->assertJsonPath('production.status','ready');
    }

    public function test_production_invalid_stock_quantity_version_dates_and_cancelled_orders_roll_back(): void
    {
        [$user,$org,$role,$store,$order,$cup,$vinyl]=$this->productionOrder(); $id=$order['id'];
        $a=collect($this->request('GET','orders/'.$id.'/materials')->json('data.allocations'))->firstWhere('inventory_detail_id',$cup);
        $p=$this->progressPayload($order,3,20,0,[['allocation_id'=>$a['id'],'quantity'=>101]]);
        $this->request('POST','orders/'.$id.'/production/events',$p)->assertStatus(422);
        $this->assertDatabaseCount('custom_job_production_events',0); $this->assertSame(3,CustomJobOrder::find($id)->version);
        $p['consumption']=[]; $p['produced']=101;
        $this->request('POST','orders/'.$id.'/production/events',$p)->assertStatus(422);
        $p['produced']=0.5;
        $this->request('POST','orders/'.$id.'/production/events',$p)->assertStatus(422);
        $p['produced']=20; $p['version']=2;
        $this->request('POST','orders/'.$id.'/production/events',$p)->assertStatus(409);
        $p['version']=3; $p['occurred_local']='2027-10-01T16:00';
        $this->request('POST','orders/'.$id.'/production/events',$p)->assertStatus(422);
        $p['occurred_local']='2026-10-01T16:00';
        $this->request('POST','orders/'.$id.'/production/events',$p)->assertOk();
        $next=$this->progressPayload($order,3,10);
        $this->request('POST','orders/'.$id.'/production/events',$next)->assertStatus(409);
        DB::table('custom_job_orders')->where('id',$id)->update(['status'=>'cancelled']);
        $next['version']=4;
        $this->request('POST','orders/'.$id.'/production/events',$next)->assertStatus(409);
        $this->assertDatabaseCount('custom_job_production_events',1);
    }

    public function test_production_permissions_tenant_and_cost_redaction(): void
    {
        [$user,$org,$role,$store,$order]=$this->materialOrder(); $id=$order['id'];
        $this->request('GET','orders/'.$id.'/production')->assertOk()->assertJsonPath('data',null);
        $this->request('POST','orders/'.$id.'/production',['version'=>1])->assertForbidden();
        $role->givePermissionTo('custom_job.production');
        $this->request('POST','orders/'.$id.'/production',['version'=>1])->assertStatus(409);
        $this->request('POST','orders/'.$id.'/confirm',['version'=>1])->assertOk();
        $this->request('POST','orders/'.$id.'/production',['version'=>2])->assertOk();
        $role->revokePermissionTo('custom_job.costs');
        $this->request('GET','orders/'.$id.'/production')->assertOk()->assertJsonMissingPath('data.actual_material_cost_nio')->assertJsonMissingPath('data.budget');
        $role->revokePermissionTo('custom_job.production');
        $this->request('POST','orders/'.$id.'/production/events',$this->progressPayload($order,3,1))->assertForbidden();
        [$other]=$this->quotesAccount();
        $this->request('GET','orders/'.$id.'/production')->assertNotFound();
    }

    private function deliveryOrder(): array
    {
        $fixture=$this->productionOrder(); $fixture[2]->givePermissionTo('custom_job.delivery');
        $this->request('POST','orders/'.$fixture[4]['id'].'/production/events',$this->progressPayload($fixture[4],3,40))->assertOk();
        return $fixture;
    }
    private function deliveryPayload(int $version,array $lines,string $note='Entrega de prueba'): array
    {
        return ['version'=>$version,'request_id'=>(string)Str::uuid(),'occurred_local'=>'2026-10-10T16:00','note'=>$note,'lines'=>$lines];
    }
    public function test_partial_delivery_is_idempotent_and_never_consumes_stock_or_invoices(): void
    {
        [$user,$org,$role,$store,$order,$cup,$vinyl]=$this->deliveryOrder();$id=$order['id'];$item=$order['items'][0]['id'];
        $p=$this->deliveryPayload(4,[['item_id'=>$item,'quantity'=>40]]);
        $this->request('POST','orders/'.$id.'/deliveries',$p)->assertOk()->assertJsonPath('delivery.items.0.pending',60)->assertJsonPath('delivery.can_invoice',false);
        $this->request('POST','orders/'.$id.'/deliveries',$p)->assertOk();
        $this->assertDatabaseCount('custom_job_delivery_events',1);$this->assertDatabaseCount('custom_job_delivery_lines',1);
        $this->assertSame(900.0,InventoryDetail::find($cup)->quantity);$this->assertSame(5200.0,InventoryDetail::find($vinyl)->quantity);$this->assertDatabaseCount('inventory_movements',2);
        $this->assertDatabaseCount('invoices',1); // Existing quotation proforma only.
        $p['lines'][0]['quantity']=39;$this->request('POST','orders/'.$id.'/deliveries',$p)->assertStatus(409);
        $next=$this->deliveryPayload(4,[['item_id'=>$item,'quantity'=>1]]);$this->request('POST','orders/'.$id.'/deliveries',$next)->assertStatus(409);
        $next['version']=5;$this->request('POST','orders/'.$id.'/deliveries',$next)->assertStatus(422);
        $this->assertSame(5,CustomJobOrder::find($id)->version);
    }
    public function test_finished_returns_can_redeliver_or_require_replacement_without_raw_stock_returns(): void
    {
        [$user,$org,$role,$store,$order,$cup]=$this->deliveryOrder();$id=$order['id'];$item=$order['items'][0]['id'];
        $d=$this->request('POST','orders/'.$id.'/deliveries',$this->deliveryPayload(4,[['item_id'=>$item,'quantity'=>40]]))->assertOk()->json('delivery');$source=$d['events'][0]['lines'][0]['id'];
        $p=$this->deliveryPayload(5,[['source_line_id'=>$source,'quantity'=>10,'disposition'=>'ready']],'Devuelto listo');
        $this->request('POST','orders/'.$id.'/deliveries/return',$p)->assertOk()->assertJsonPath('delivery.items.0.available',10)->assertJsonPath('delivery.items.0.delivered_net',30);
        $this->request('POST','orders/'.$id.'/deliveries/return',$p)->assertOk();
        $this->request('POST','orders/'.$id.'/deliveries',$this->deliveryPayload(6,[['item_id'=>$item,'quantity'=>10]]))->assertOk();
        $p=$this->deliveryPayload(7,[['source_line_id'=>$source,'quantity'=>5,'disposition'=>'discard']],'Piezas dañadas');
        $this->request('POST','orders/'.$id.'/deliveries/return',$p)->assertOk()->assertJsonPath('delivery.items.0.available',0)->assertJsonPath('delivery.items.0.unavailable_returned',5);
        $this->assertSame(900.0,InventoryDetail::find($cup)->quantity);$this->assertDatabaseCount('inventory_movements',2);
        $this->request('POST','orders/'.$id.'/production/events',$this->progressPayload($order,8,65))->assertOk()->assertJsonPath('production.items.0.produced',100)->assertJsonPath('production.items.0.manufactured',105);
        $this->request('POST','orders/'.$id.'/deliveries',$this->deliveryPayload(9,[['item_id'=>$item,'quantity'=>65]]))->assertOk()->assertJsonPath('delivery.can_invoice',true)->assertJsonPath('delivery.items.0.delivered_net',100);
        $this->assertDatabaseCount('invoices',1);
    }
    public function test_delivery_and_production_corrections_preserve_history_and_reject_invalid_balances(): void
    {
        [$user,$org,$role,$store,$order]=$this->deliveryOrder();$id=$order['id'];$item=$order['items'][0]['id'];
        $original=$this->request('GET','orders/'.$id.'/production')->json('data.events.0.id');
        $d=$this->request('POST','orders/'.$id.'/deliveries',$this->deliveryPayload(4,[['item_id'=>$item,'quantity'=>20]]))->assertOk()->json('delivery');$source=$d['events'][0]['lines'][0]['id'];
        $this->request('POST','orders/'.$id.'/production/events',[...$this->progressPayload($order,5,0),'corrects_event_id'=>$original,'note'=>'Conteo erróneo'])->assertStatus(422);
        $r=$this->request('POST','orders/'.$id.'/deliveries/return',$this->deliveryPayload(5,[['source_line_id'=>$source,'quantity'=>5,'disposition'=>'ready']],'Devuelto'))->assertOk()->json('delivery');
        $returnLine=collect($r['events'])->firstWhere('kind','return')['lines'][0]['id'];
        $correction=$this->deliveryPayload(6,[['source_line_id'=>$returnLine]],'Devolución anotada por error');
        $this->request('POST','orders/'.$id.'/deliveries/correct-return',$correction)->assertOk()->assertJsonPath('delivery.items.0.delivered_net',20);
        $this->request('POST','orders/'.$id.'/deliveries/correct-return',$correction)->assertOk();
        $correction['version']=7;$correction['request_id']=(string)Str::uuid();$this->request('POST','orders/'.$id.'/deliveries/correct-return',$correction)->assertStatus(422);
        $this->request('POST','orders/'.$id.'/deliveries/correct',$this->deliveryPayload(7,[['source_line_id'=>$source]],'Entrega anotada por error'))->assertOk()->assertJsonPath('delivery.items.0.delivered_net',0);
        $this->request('POST','orders/'.$id.'/production/events',[...$this->progressPayload($order,8,0),'corrects_event_id'=>$original,'note'=>'Conteo erróneo'])->assertOk();
        $this->assertDatabaseCount('custom_job_delivery_events',4);
    }
    public function test_delivery_batch_is_atomic_and_all_items_are_required_for_completion(): void
    {
        [$user,$org,$role,$store,$order]=$this->deliveryOrder();$id=$order['id'];$item=$order['items'][0]['id'];
        // The item exists in the same order; exercise multi-item delivery independently of quotation authoring.
        $extra=CustomJobOrder::find($id)->items()->create(['position'=>1,'name'=>'Tarjetas','quantity'=>2,'commercial'=>[],'composition'=>['materials'=>[]]]);
        $p=$this->deliveryPayload(4,[['item_id'=>$item,'quantity'=>40],['item_id'=>$extra->id,'quantity'=>1]]);
        $this->request('POST','orders/'.$id.'/deliveries',$p)->assertStatus(422);$this->assertDatabaseCount('custom_job_delivery_lines',0);
        $this->request('POST','orders/'.$id.'/deliveries',$this->deliveryPayload(4,[['item_id'=>$item,'quantity'=>40]]))->assertOk()->assertJsonPath('delivery.can_invoice',false);
        // Simulate the revised quantity owned by plan 11, without implementing its edit flow here.
        DB::table('custom_job_order_items')->where('id',$item)->update(['quantity'=>50]);
        $this->request('POST','orders/'.$id.'/production/events',$this->progressPayload($order,5,10))->assertOk();
        $card=[...$this->progressPayload($order,6,2),'item_id'=>$extra->id];$this->request('POST','orders/'.$id.'/production/events',$card)->assertOk();
        $this->request('POST','orders/'.$id.'/deliveries',$this->deliveryPayload(7,[['item_id'=>$item,'quantity'=>10]]))->assertOk()->assertJsonPath('delivery.can_invoice',false);
        $this->request('POST','orders/'.$id.'/deliveries',$this->deliveryPayload(8,[['item_id'=>$extra->id,'quantity'=>2]]))->assertOk()->assertJsonPath('delivery.can_invoice',true);
    }
    public function test_delivery_permissions_returns_maxima_dates_cancelled_and_tenant(): void
    {
        [$user,$org,$role,$store,$order]=$this->deliveryOrder();$id=$order['id'];$item=$order['items'][0]['id'];
        $p=$this->deliveryPayload(4,[['item_id'=>$item,'quantity'=>1]]);$p['occurred_local']='2027-10-11T16:00';$this->request('POST','orders/'.$id.'/deliveries',$p)->assertStatus(422);
        $role->revokePermissionTo('custom_job.delivery');$this->request('POST','orders/'.$id.'/deliveries',$p)->assertForbidden();$role->givePermissionTo('custom_job.delivery');
        $d=$this->request('POST','orders/'.$id.'/deliveries',$this->deliveryPayload(4,[['item_id'=>$item,'quantity'=>1]]))->assertOk()->json('delivery');$source=$d['events'][0]['lines'][0]['id'];
        $p=$this->deliveryPayload(5,[['source_line_id'=>$source,'quantity'=>2,'disposition'=>'ready']],'Devolución');$this->request('POST','orders/'.$id.'/deliveries/return',$p)->assertStatus(422);
        $p['lines'][0]['quantity']=1;$p['lines'][0]['disposition']='raw_inventory';$this->request('POST','orders/'.$id.'/deliveries/return',$p)->assertStatus(422);$p['lines'][0]['disposition']='hold';
        $p['occurred_local']='2026-10-09T16:00';$this->request('POST','orders/'.$id.'/deliveries/return',$p)->assertStatus(422);$p['occurred_local']='2026-10-10T16:00';
        DB::table('custom_job_orders')->where('id',$id)->update(['status'=>'cancelled']);
        $this->request('POST','orders/'.$id.'/deliveries',$this->deliveryPayload(5,[['item_id'=>$item,'quantity'=>1]]))->assertStatus(409);
        $this->request('POST','orders/'.$id.'/deliveries/return',$p)->assertOk()->assertJsonPath('delivery.can_invoice',false);
        $this->request('POST','orders/'.$id.'/deliveries/return',[...$p,'version'=>6,'request_id'=>(string)Str::uuid()])->assertStatus(422);
        [$other]=$this->quotesAccount();$this->request('GET','orders/'.$id.'/deliveries')->assertNotFound();
    }

    public function test_return_correction_cannot_overdeliver_or_hide_manufactured_replacements(): void
    {
        [$user,$org,$role,$store,$order]=$this->deliveryOrder();$id=$order['id'];$item=$order['items'][0]['id'];
        $d=$this->request('POST','orders/'.$id.'/deliveries',$this->deliveryPayload(4,[['item_id'=>$item,'quantity'=>40]]))->assertOk()->json('delivery');$source=$d['events'][0]['lines'][0]['id'];
        $r=$this->request('POST','orders/'.$id.'/deliveries/return',$this->deliveryPayload(5,[['source_line_id'=>$source,'quantity'=>10,'disposition'=>'ready']],'Devuelto listo'))->assertOk()->json('delivery');
        $ready=collect($r['events'])->firstWhere('kind','return')['lines'][0]['id'];
        $this->request('POST','orders/'.$id.'/deliveries',$this->deliveryPayload(6,[['item_id'=>$item,'quantity'=>10]]))->assertOk();
        $this->request('POST','orders/'.$id.'/deliveries/correct-return',$this->deliveryPayload(7,[['source_line_id'=>$ready]],'Error de devolución'))->assertStatus(422);
        $r=$this->request('POST','orders/'.$id.'/deliveries/return',$this->deliveryPayload(7,[['source_line_id'=>$source,'quantity'=>5,'disposition'=>'discard']],'Dañado'))->assertOk()->json('delivery');
        $bad=collect($r['events'])->where('kind','return')->flatMap(fn($e)=>$e['lines'])->firstWhere('disposition','discard')['id'];
        $this->request('POST','orders/'.$id.'/production/events',$this->progressPayload($order,8,65))->assertOk();
        $this->request('POST','orders/'.$id.'/deliveries/correct-return',$this->deliveryPayload(9,[['source_line_id'=>$bad]],'Error de devolución'))->assertStatus(422);
        $this->assertSame(9,CustomJobOrder::find($id)->version);
        $this->request('GET','orders/'.$id.'/deliveries')->assertOk()->assertJsonPath('data.items.0.unavailable_returned',5)->assertJsonPath('data.items.0.delivered_net',35);
    }

    private function revisionPayload(array $order,int $version,int $quantity): array
    {
        return ['version'=>$version,'delivery_local'=>$order['delivery_local'],'reason'=>'Cambio solicitado','items'=>[['id'=>$order['items'][0]['id'],'quantity'=>$quantity]],
            'returns'=>[],'selections'=>[],'excess'=>[],'restore_excess'=>[]];
    }
    private function previewRevision(array $order,array $payload): array
    {
        return $this->request('POST','orders/'.$order['id'].'/revisions/preview',$payload)->assertOk()->json('data');
    }
    private function applyRevision(array $order,array $payload,array $preview): array
    {
        $p=[...$payload,'request_id'=>(string)Str::uuid(),'preview_digest'=>$preview['digest']];
        $this->request('POST','orders/'.$order['id'].'/revisions',$p)->assertOk();
        return $p;
    }
    public function test_revision_reduces_100_to_50_returns_only_unused_and_preserves_agreed_price_history(): void
    {
        [$user,$org,$role,$store,$order,$cup,$vinyl]=$this->productionOrder();$id=$order['id'];
        $alloc=$this->request('GET','orders/'.$id.'/materials')->json('data.allocations');$cupA=collect($alloc)->firstWhere('inventory_detail_id',$cup);$vinylA=collect($alloc)->firstWhere('inventory_detail_id',$vinyl);
        $this->request('POST','orders/'.$id.'/production/events',$this->progressPayload($order,3,40,0,[['allocation_id'=>$cupA['id'],'quantity'=>40],['allocation_id'=>$vinylA['id'],'quantity'=>1920]]))->assertOk();
        $payload=$this->revisionPayload($order,4,50);$preview=$this->previewRevision($order,$payload);
        $this->assertSame(10,$preview['items'][0]['pending']);
        $this->assertSame(50,collect($preview['materials'])->firstWhere('id',$cupA['id'])['proposed_return']);
        $this->assertSame(2400,collect($preview['materials'])->firstWhere('id',$vinylA['id'])['proposed_return']);
        $this->assertSame($order['commercial']['articles'][0]['unit_price'],$preview['after']['articles'][0]['unit_price']);
        $this->assertSame($order['commercial']['totals']['total']/2,$preview['after']['totals']['total']);
        $this->assertSame(900.0,InventoryDetail::find($cup)->quantity);$this->assertDatabaseCount('custom_job_order_revisions',0);
        $payload['returns']=[['allocation_id'=>$cupA['id'],'quantity'=>50],['allocation_id'=>$vinylA['id'],'quantity'=>2400]];
        $applied=$this->applyRevision($order,$payload,$preview);
        $this->request('POST','orders/'.$id.'/revisions',$applied)->assertOk();$this->assertDatabaseCount('custom_job_order_revisions',1);
        $this->assertSame(950.0,InventoryDetail::find($cup)->quantity);$this->assertSame(7600.0,InventoryDetail::find($vinyl)->quantity);
        $this->request('GET','orders/'.$id.'/production')->assertOk()->assertJsonPath('data.items.0.produced',40)->assertJsonPath('data.items.0.pending',10);
        $ledger=$this->request('GET','orders/'.$id.'/materials')->json('data.allocations');$this->assertSame(10,collect($ledger)->firstWhere('id',$cupA['id'])['remaining']);$this->assertSame(480,collect($ledger)->firstWhere('id',$vinylA['id'])['remaining']);
        $this->request('GET','orders/'.$id.'/revisions')->assertOk()->assertJsonPath('data.0.before.items.0.quantity',100)->assertJsonPath('data.0.after.items.0.quantity',50);
        $this->assertDatabaseCount('invoices',1);
        $applied['reason']='Otro cambio';$this->request('POST','orders/'.$id.'/revisions',$applied)->assertStatus(409);
        $current=$this->request('GET','orders/'.$id)->json('data');$grow=$this->revisionPayload($order,$current['version'],80);$next=$this->previewRevision($order,$grow);
        $this->assertSame(30,$next['additional'][0]['needed']-$next['additional'][0]['remaining']);
        $this->applyRevision($order,$grow,$next);$this->assertSame(920.0,InventoryDetail::find($cup)->quantity);
    }
    public function test_revision_prepared_consumption_and_partial_or_zero_returns_do_not_restore_used_stock(): void
    {
        [$user,$org,$role,$store,$order,$cup]=$this->productionOrder();$id=$order['id'];
        $a=collect($this->request('GET','orders/'.$id.'/materials')->json('data.allocations'))->firstWhere('inventory_detail_id',$cup);
        $this->request('POST','orders/'.$id.'/production/events',$this->progressPayload($order,3,0,0,[['allocation_id'=>$a['id'],'quantity'=>70]]))->assertOk();
        $payload=$this->revisionPayload($order,4,50);$preview=$this->previewRevision($order,$payload);$row=collect($preview['materials'])->firstWhere('id',$a['id']);
        $this->assertSame(70,$row['used']);$this->assertSame(0,$row['needed']);$this->assertSame(30,$row['proposed_return']);
        $payload['returns']=[['allocation_id'=>$a['id'],'quantity'=>0]];$this->applyRevision($order,$payload,$preview);
        $this->assertSame(900.0,InventoryDetail::find($cup)->quantity);
        $this->request('GET','orders/'.$id.'/materials')->assertOk()->assertJsonPath('data.allocations.0.used',70);
    }
    public function test_revision_surplus_destination_and_explicit_restore_preserve_manufacturing(): void
    {
        [$user,$org,$role,$store,$order]=$this->deliveryOrder();$id=$order['id'];$item=$order['items'][0]['id'];
        $this->request('POST','orders/'.$id.'/deliveries',$this->deliveryPayload(4,[['item_id'=>$item,'quantity'=>20]]))->assertOk();
        $payload=$this->revisionPayload($order,5,30);$preview=$this->previewRevision($order,$payload);$this->assertSame(10,$preview['items'][0]['excess']);
        $invalid=[...$payload,'request_id'=>(string)Str::uuid(),'preview_digest'=>$preview['digest']];$this->request('POST','orders/'.$id.'/revisions',$invalid)->assertStatus(422);
        $payload['excess']=[$item=>'hold'];$this->applyRevision($order,$payload,$preview);
        $this->request('GET','orders/'.$id.'/production')->assertOk()->assertJsonPath('data.items.0.manufactured',40)->assertJsonPath('data.items.0.produced',30)->assertJsonPath('data.items.0.surplus',10);
        $current=$this->request('GET','orders/'.$id)->json('data');$grow=$this->revisionPayload($order,$current['version'],50);$held=$this->previewRevision($order,$grow)['holds'][0];
        $grow['restore_excess']=[['source_event_id'=>$held['id'],'quantity'=>10]];$next=$this->previewRevision($order,$grow);$this->applyRevision($order,$grow,$next);
        $this->request('GET','orders/'.$id.'/production')->assertOk()->assertJsonPath('data.items.0.manufactured',40)->assertJsonPath('data.items.0.produced',40);
        $current=$this->request('GET','orders/'.$id)->json('data');$bad=$this->revisionPayload($order,$current['version'],19);$this->request('POST','orders/'.$id.'/revisions/preview',$bad)->assertStatus(422);
    }
    public function test_revision_recipe_slots_new_items_removal_and_explicit_price_are_stable(): void
    {
        [$user,$org,$role,$store,$order,$cup,$vinyl]=$this->productionOrder();$id=$order['id'];$item=$order['items'][0]['id'];
        $newSource=$this->source($store,'Tarjeta',2);$recipe=$this->article([$this->material($newSource)]);
        $payload=$this->revisionPayload($order,3,50);$payload['items'][0]['composition']=$recipe;$payload['items'][0]['price']=['method'=>'explicit','unit_price'=>4.12345678];
        $preview=$this->previewRevision($order,$payload);$this->assertSame(4.12345678,$preview['after']['articles'][0]['unit_price']);$newSlot=$preview['additional'][0]['material_index'];$this->assertGreaterThan(1,$newSlot);
        $payload['returns']=array_map(fn($a)=>['allocation_id'=>$a['id'],'quantity'=>$a['proposed_return']],$preview['materials']);$this->applyRevision($order,$payload,$preview);
        $this->assertSame(1000.0,InventoryDetail::find($cup)->quantity);
        $this->request('GET','orders/'.$id.'/materials')->assertOk()->assertJsonPath('data.requirements.0.material_index',$newSlot);
        $current=$this->request('GET','orders/'.$id)->json('data');$newId=(string)Str::uuid();$replace=$this->revisionPayload($order,$current['version'],0);
        $replace['items'][]=['id'=>$newId,'quantity'=>2,'composition'=>$this->article([]),'price'=>['method'=>'explicit','unit_price'=>15]];
        $p=$this->previewRevision($order,$replace);$this->applyRevision($order,$replace,$p);
        $this->assertDatabaseHas('custom_job_order_items',['id'=>$item,'active'=>false,'quantity'=>0]);$this->assertDatabaseHas('custom_job_order_items',['id'=>$newId,'active'=>true,'quantity'=>2]);
    }
    public function test_revision_stale_preview_overreturns_failures_permissions_and_billed_document_boundaries(): void
    {
        [$user,$org,$role,$store,$order,$cup]=$this->productionOrder();$id=$order['id'];$p=$this->revisionPayload($order,3,50);$preview=$this->previewRevision($order,$p);
        $a=$preview['materials'][0];$bad=[...$p,'request_id'=>(string)Str::uuid(),'preview_digest'=>$preview['digest'],'returns'=>[['allocation_id'=>$a['id'],'quantity'=>$a['remaining']]]];
        $this->request('POST','orders/'.$id.'/revisions',$bad)->assertStatus(422);$this->assertSame(900.0,InventoryDetail::find($cup)->quantity);
        $stale=[...$p,'request_id'=>(string)Str::uuid(),'preview_digest'=>str_repeat('0',64)];$this->request('POST','orders/'.$id.'/revisions',$stale)->assertStatus(409);
        $role->revokePermissionTo('custom_job.costs');$noCost=$this->previewRevision($order,$p);$this->assertArrayNotHasKey('snapshot',$noCost);
        $recipe=$p;$recipe['items'][0]['composition']=$this->article([]);$this->request('POST','orders/'.$id.'/revisions/preview',$recipe)->assertForbidden();
        $role->revokePermissionTo('custom_job.order');$this->request('POST','orders/'.$id.'/revisions/preview',$p)->assertForbidden();$role->givePermissionTo('custom_job.order');
        $role->revokePermissionTo('custom_job.return');$needsReturn=[...$p,'request_id'=>(string)Str::uuid(),'preview_digest'=>$preview['digest'],'returns'=>[['allocation_id'=>$a['id'],'quantity'=>1]]];$this->request('POST','orders/'.$id.'/revisions',$needsReturn)->assertForbidden();
        Invoice::where('custom_job_quote_id',$order['quote_id'])->update(['invoice_type'=>'cash','invoice_status'=>'completed']);$this->request('POST','orders/'.$id.'/revisions/preview',$p)->assertStatus(409);
        $this->assertDatabaseCount('custom_job_order_revisions',0);
    }

    public function test_revision_financial_projection_and_failure_roll_back_every_stock_and_commercial_write(): void
    {
        [$user,$org,$role,$store,$order,$cup]=$this->productionOrder();$id=$order['id'];
        $adapter=new class implements \App\Contracts\CustomJobOrderRevisionFinancial {
            public bool $fail=true;
            public function preview(User $user,CustomJobOrder $order,array $commercial): array {return ['payments'=>['balance'=>$commercial['totals']['total']-1000,'credit'=>1000-$commercial['totals']['total'],'currency'=>$commercial['currency']],'billing'=>['editable'=>true]];}
            public function apply(User $user,CustomJobOrder $order,\App\Models\CustomJobOrderRevision $revision): void {abort_if($this->fail,409,'El saldo cambió durante la revisión.');}
        };
        app()->instance(\App\Contracts\CustomJobOrderRevisionFinancial::class,$adapter);
        $payload=$this->revisionPayload($order,3,50);$preview=$this->previewRevision($order,$payload);$this->assertGreaterThan(0,$preview['financial']['payments']['credit']);
        $payload['returns']=array_map(fn($a)=>['allocation_id'=>$a['id'],'quantity'=>$a['proposed_return']],$preview['materials']);
        $apply=[...$payload,'request_id'=>(string)Str::uuid(),'preview_digest'=>$preview['digest']];
        $this->request('POST','orders/'.$id.'/revisions',$apply)->assertStatus(409);
        $this->assertDatabaseCount('custom_job_order_revisions',0);$this->assertSame(900.0,InventoryDetail::find($cup)->quantity);$this->assertSame(100.0,CustomJobOrder::find($id)->items()->first()->quantity);
        $adapter->fail=false;$this->request('POST','orders/'.$id.'/revisions',$apply)->assertOk();$this->assertSame(950.0,InventoryDetail::find($cup)->quantity);
        $role->revokePermissionTo('custom_job.costs');$this->request('GET','orders/'.$id.'/revisions')->assertOk()->assertJsonMissingPath('data.0.before.snapshot')->assertJsonMissingPath('data.0.before.items.0.composition')->assertJsonMissingPath('data.0.after.snapshot');
        app()->forgetInstance(\App\Contracts\CustomJobOrderRevisionFinancial::class);
    }
    public function test_revision_cost_changes_invalidate_preview_and_composition_changes_keep_old_material_usage(): void
    {
        [$user,$org,$role,$store,$order,$cup]=$this->productionOrder();$id=$order['id'];
        $a=collect($this->request('GET','orders/'.$id.'/materials')->json('data.allocations'))->firstWhere('inventory_detail_id',$cup);
        $this->request('POST','orders/'.$id.'/production/events',$this->progressPayload($order,3,40,0,[['allocation_id'=>$a['id'],'quantity'=>40]]))->assertOk();
        $payload=$this->revisionPayload($order,4,50);$preview=$this->previewRevision($order,$payload);Product::find(InventoryDetail::find($cup)->product_id)->update(['cost'=>6]);
        $this->request('POST','orders/'.$id.'/revisions',[...$payload,'request_id'=>(string)Str::uuid(),'preview_digest'=>$preview['digest']])->assertStatus(409);
        $new=$this->source($store,'Adorno',2);$payload['items'][0]['composition']=$this->article([$this->material($new)]);$preview=$this->previewRevision($order,$payload);
        $this->assertSame(10,$preview['additional'][0]['needed']);$payload['returns']=array_map(fn($a)=>['allocation_id'=>$a['id'],'quantity'=>$a['proposed_return']],$preview['materials']);
        $this->applyRevision($order,$payload,$preview);$ledger=$this->request('GET','orders/'.$id.'/materials')->json('data');
        $this->assertSame(40,collect($ledger['allocations'])->firstWhere('id',$a['id'])['used']);$this->assertSame(0,collect($ledger['allocations'])->firstWhere('id',$a['id'])['remaining']);
        $this->request('GET','orders/'.$id.'/production')->assertOk()->assertJsonPath('data.items.0.produced',40)->assertJsonPath('data.items.0.pending',10);
    }

    public function test_revision_changed_cut_does_not_reuse_transformed_material_preparation(): void
    {
        [$user,$org,$role,$store,$order,$cup,$vinyl]=$this->productionOrder();$id=$order['id'];
        $a=collect($this->request('GET','orders/'.$id.'/materials')->json('data.allocations'))->firstWhere('inventory_detail_id',$vinyl);
        $this->request('POST','orders/'.$id.'/production/events',$this->progressPayload($order,3,0,0,[['allocation_id'=>$a['id'],'quantity'=>4800]]))->assertOk();
        $recipe=$this->article([$this->material($cup),$this->material($vinyl,['method'=>'area','width'=>12,'height'=>8])]);
        $p=$this->revisionPayload($order,4,50);$p['items'][0]['composition']=$recipe;$view=$this->previewRevision($order,$p);
        $n=collect($view['additional'])->firstWhere('material_index',1);$this->assertSame(4800,$n['needed']);
        $this->assertSame(4800,collect($view['materials'])->firstWhere('id',$a['id'])['used']);
    }


    private function paymentPayload(int $version,float $amount=500,string $currency='NIO'): array
    {
        return ['version'=>$version,'request_id'=>(string)Str::uuid(),'amount'=>$amount,'currency'=>$currency,'cash_session_id'=>null,'reason'=>'Anticipo acordado','payment_method'=>'CASH','payment_metadata'=>[]];
    }
    public function test_payments_mixed_metadata_cash_totals_retry_and_no_sale_or_stock_effects(): void
    {
        [$user,$org,$role,$store,$order,$cup,$vinyl]=$this->materialOrder(); $role->givePermissionTo('custom_job.payment');
        $this->request('POST','orders/'.$order['id'].'/confirm',['version'=>1])->assertOk();
        $box=\App\Models\CashSession::create(['store_id'=>$store->id,'user_id'=>$user->id,'status'=>'open','cash_register_name'=>'Caja pruebas','opened_at'=>now()]);
        $p=$this->paymentPayload(2); $p['cash_session_id']=$box->id; $p['payment_method']='MULTIPLE';
        $p['payment_metadata']=['payments'=>[['method'=>'CASH','amount'=>200,'paid_in_usd'=>10,'paid_in_nio'=>0],['method'=>'TRANSFER','amount'=>200,'bank'=>'Banco','reference'=>'T1'],['method'=>'CARD','amount'=>100,'card_last_four'=>'1234','reference'=>'C1']]];
        $this->request('POST','orders/'.$order['id'].'/payments',$p)->assertOk()->assertJsonPath('payments.summary.paid',500)->assertJsonPath('data.version',3)->assertJsonPath('payments.events.0.payment_metadata.payments.0.change_nio',165);
        $this->request('POST','orders/'.$order['id'].'/payments',$p)->assertOk(); $p['amount']=501;
        $this->request('POST','orders/'.$order['id'].'/payments',$p)->assertStatus(409);
        $this->assertDatabaseCount('custom_job_payments',1); $this->assertDatabaseCount('invoices',1); $this->assertDatabaseCount('inventory_movements',2);
        $this->assertSame(900.0,InventoryDetail::find($cup)->quantity);
        $totals=\App\Services\CustomJobPayments::sessionTotals($box->id);
        $this->assertSame(['cash_nio'=>-165.0,'cash_usd'=>10.0,'transfer'=>200.0,'card'=>100.0],$totals);
        Schema::table('cash_sessions',function(Blueprint $t){
            foreach(['opening_balance','expected_balance','actual_cash','actual_usd','expected_usd','usd_exchange_rate','difference'] as $column) $t->decimal($column,20,2)->default(0);
            $t->timestamp('closed_at')->nullable();$t->text('notes')->nullable();
        });
        $box->update(['opening_balance'=>200]);
        Schema::create('credit_details',function(Blueprint $t){$t->uuid('id')->primary();$t->uuid('organization_id');$t->uuid('cash_session_id')->nullable();$t->timestamp('voided_at')->nullable();});
        Schema::create('cash_transactions',function(Blueprint $t){$t->uuid('id')->primary();$t->uuid('organization_id');$t->uuid('cash_session_id');$t->string('type');$t->string('currency');$t->decimal('amount',20,2);});
        $box->opening_balance=200;
        $method=new \ReflectionMethod(\App\Http\Controllers\Api\V1\CashSessionController::class,'computeSessionTotals');
        $actual=$method->invoke(new \App\Http\Controllers\Api\V1\CashSessionController,$box);
        $this->assertSame(35.0,(float)$actual['expected_cash_nio']); $this->assertSame(10.0,(float)$actual['expected_cash_usd']);
        $this->assertSame(200.0,(float)$actual['total_transfer']);$this->assertSame(100.0,(float)$actual['total_card']);
        $this->request('POST','orders/'.$order['id'].'/payments',$this->paymentPayload(2,10))->assertStatus(409);
        $rest=CustomJobOrder::find($order['id'])->commercial['totals']['total']-500;
        $this->request('POST','orders/'.$order['id'].'/payments',$this->paymentPayload(3,$rest))->assertOk()->assertJsonPath('payments.summary.pending',0)->assertJsonPath('data.status','confirmed');
        $this->assertDatabaseCount('custom_job_work_orders',0);
        $this->request('POST','orders/'.$order['id'].'/payments',$this->paymentPayload(4,1))->assertStatus(422);
        $this->postJson('/api/v1/cash-sessions/close',['cash_session_id'=>$box->id,'actual_cash'=>35,'actual_usd'=>10,'usd_exchange_rate'=>36.5])->assertOk()->assertJsonPath('session.status','closed')->assertJsonPath('totals.expected_cash_nio',35)->assertJsonPath('session.difference',0);
        $this->assertDatabaseCount('custom_job_payments',2);

    }
    public function test_payment_reduction_real_credit_refund_partial_retries_and_limits(): void
    {
        [$user,$org,$role,$store,$order]=$this->materialOrder(); $role->givePermissionTo('custom_job.payment');
        $id=$order['id']; $this->request('POST','orders/'.$id.'/confirm',['version'=>1])->assertOk();
        $saved=$this->request('POST','orders/'.$id.'/payments',$this->paymentPayload(2))->assertOk()->json(); $source=$saved['payments']['events'][0]['id'];
        $p=$this->revisionPayload($order,3,50); $v=$this->previewRevision($order,$p); $this->assertGreaterThan(0,$v['financial']['payments']['credit']);
        $this->applyRevision($order,$p,$v); $version=CustomJobOrder::find($id)->version;
        $view=$this->request('GET','orders/'.$id.'/payments')->assertOk()->json('data'); $credit=$view['summary']['credit'];
        $refund=[...$this->paymentPayload($version,50),'source_payment_id'=>$source,'reason'=>'Devolver exceso por reducción'];
        $this->request('POST','orders/'.$id.'/payments/refund',$refund)->assertOk()->assertJsonPath('payments.summary.refunded',50)->assertJsonPath('payments.events.0.refundable_amount',450);
        $this->request('POST','orders/'.$id.'/payments/refund',$refund)->assertOk();
        $this->request('POST','orders/'.$id.'/payments/refund',[...$refund,'request_id'=>(string)Str::uuid(),'version'=>$version+1,'amount'=>$credit])->assertStatus(422);
        $this->request('POST','orders/'.$id.'/payments/refund',[...$refund,'request_id'=>(string)Str::uuid(),'version'=>$version+1,'amount'=>$credit-50])->assertOk()->assertJsonPath('payments.summary.credit',0);
        $this->assertDatabaseCount('custom_job_payments',3);
        $this->assertDatabaseCount('custom_job_order_revisions',1);
        $this->assertDatabaseCount('invoices',1);
    }
    public function test_payments_session_policy_currency_snapshot_and_authorization(): void
    {
        [$user,$org,$role,$store,$order]=$this->materialOrder(); $id=$order['id']; $role->givePermissionTo('custom_job.payment');
        $this->request('POST','orders/'.$id.'/payments',$this->paymentPayload(1))->assertStatus(409);
        $this->request('POST','orders/'.$id.'/confirm',['version'=>1])->assertOk();
        Setting::create(['key'=>'cash_control_mode','type'=>'global','value'=>'STRICT']);
        $this->request('POST','orders/'.$id.'/payments',$this->paymentPayload(2))->assertStatus(422);
        $closed=\App\Models\CashSession::create(['store_id'=>$store->id,'user_id'=>$user->id,'status'=>'closed']);
        $this->request('POST','orders/'.$id.'/payments',[...$this->paymentPayload(2),'cash_session_id'=>$closed->id])->assertStatus(422);
        $closed->update(['status'=>'open']);
        $p=[...$this->paymentPayload(2,10,'USD'),'cash_session_id'=>$closed->id];
        $this->request('POST','orders/'.$id.'/payments',$p)->assertOk()->assertJsonPath('payments.summary.paid_nio',365)->assertJsonPath('payments.events.0.amount','10.00');
        Setting::create(['key'=>'usd_exchange_rate','type'=>'global','value'=>'40']);
        $this->request('GET','orders/'.$id.'/payments')->assertOk()->assertJsonPath('data.summary.paid_nio',365)->assertJsonPath('data.current_exchange_rate',40);
        $role->revokePermissionTo('custom_job.payment'); $this->authenticate($user);
        $this->request('POST','orders/'.$id.'/payments',[...$p,'version'=>3,'request_id'=>(string)Str::uuid()])->assertForbidden();
        $this->request('GET','orders/'.$id.'/payments')->assertOk();
        $role->givePermissionTo('custom_job.payment'); $role->revokePermissionTo('custom_job.return'); $this->authenticate($user);
        $source=\App\Models\CustomJobPayment::first()->id;
        $this->request('POST','orders/'.$id.'/payments/refund',[...$this->paymentPayload(3,1,'USD'),'source_payment_id'=>$source,'cash_session_id'=>$closed->id])->assertForbidden();
        $this->request('POST','orders/'.$id.'/payments/annul',['version'=>3])->assertNotFound();
        $this->assertDatabaseCount('custom_job_payments',1);
    }
    public function test_payments_invalid_metadata_is_atomic_and_other_tenants_are_inaccessible(): void
    {
        [$user,$org,$role,$store,$order]=$this->materialOrder(); $id=$order['id']; $role->givePermissionTo('custom_job.payment');
        $this->request('POST','orders/'.$id.'/confirm',['version'=>1])->assertOk();
        foreach([['payment_method'=>'TRANSFER'],['payment_method'=>'CARD','payment_metadata'=>['card_last_four'=>'1234']],['payment_method'=>'MULTIPLE','payment_metadata'=>['payments'=>[['method'=>'CREDIT','amount'=>500]]]],['amount'=>0.001],['payment_metadata'=>['amount'=>1]],['payment_metadata'=>['paid_in_nio'=>1]]] as $bad) {
            $this->request('POST','orders/'.$id.'/payments',[...$this->paymentPayload(2),...$bad])->assertStatus(422);
        }
        $this->assertDatabaseCount('custom_job_payments',0); $this->assertSame(2,CustomJobOrder::find($id)->version);
        [$other,$otherOrg]=$this->startAccount(['custom_job.index','custom_job.payment']);
        $this->request('GET','orders/'.$id.'/payments')->assertNotFound();
        $this->request('POST','orders/'.$id.'/payments',$this->paymentPayload(2))->assertNotFound();
    }

    public function test_refunds_keep_original_rate_and_settle_rounding_after_cancellation_without_reopening_old_cash(): void
    {
        [$user,$org,$role,$store,$order]=$this->materialOrder(); $id=$order['id']; $role->givePermissionTo('custom_job.payment');
        $this->request('POST','orders/'.$id.'/confirm',['version'=>1])->assertOk();
        $box=\App\Models\CashSession::create(['store_id'=>$store->id,'user_id'=>$user->id,'status'=>'open']);
        $saved=$this->request('POST','orders/'.$id.'/payments',[...$this->paymentPayload(2,0.02,'USD'),'cash_session_id'=>$box->id])->assertOk()->json();
        $source=$saved['payments']['events'][0]['id'];
        $raw=\App\Models\CustomJobPayment::find($source)->getRawOriginal('occurred_at');
        $this->assertSame(str_replace(' ','T',$raw).'Z',$saved['payments']['events'][0]['occurred_at']);
        $box->update(['status'=>'closed']);
        // Simulate the terminal state owned by plan 13; no cancellation endpoint is added here.
        CustomJobOrder::find($id)->update(['status'=>'canceled']);
        Setting::create(['key'=>'usd_exchange_rate','type'=>'global','value'=>'40']);
        Setting::create(['key'=>'accepted_currencies','type'=>'global','value'=>'["NIO"]']);
        $p=[...$this->paymentPayload(3,0.01,'USD'),'source_payment_id'=>$source];
        $this->request('POST','orders/'.$id.'/payments/refund',[...$p,'cash_session_id'=>$box->id])->assertStatus(422);
        $this->request('POST','orders/'.$id.'/payments/refund',$p)->assertOk()->assertJsonPath('payments.events.1.exchange_rate','36.50000000');
        $this->request('POST','orders/'.$id.'/payments/refund',[...$p,'version'=>4,'request_id'=>(string)Str::uuid()])->assertOk()->assertJsonPath('payments.summary.net_paid',0)->assertJsonPath('payments.events.0.refundable_amount',0);
        $this->assertSame(0.73,(float)\App\Models\CustomJobPayment::where('kind','refund')->sum('amount_nio'));
        $this->request('POST','orders/'.$id.'/payments',[...$this->paymentPayload(5,1)])->assertStatus(409);
        $this->assertSame(0.02,\App\Services\CustomJobPayments::sessionTotals($box->id)['cash_usd']);
    }
}
