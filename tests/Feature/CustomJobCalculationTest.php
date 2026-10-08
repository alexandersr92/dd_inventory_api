<?php

namespace Tests\Feature;

use App\Http\Resources\InvoiceResource;
use App\Models\Client;
use App\Models\CustomJobRate;
use App\Models\Inventory;
use App\Models\InventoryDetail;
use App\Models\Invoice;
use App\Models\Module;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Store;
use App\Models\User;
use App\Services\TenantManager;
use App\Support\CustomJobProforma;
use App\Support\CustomJobs;
use Database\Seeders\CustomJobsAccessSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
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
}
