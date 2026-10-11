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
            ->assertJsonPath('data.confirmation.available', true)->assertJsonPath('data.operations.payment', null)->json('data');
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
}
