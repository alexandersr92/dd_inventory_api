<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Organization;
use App\Models\Module;
use App\Models\Store;
use App\Models\User;
use App\Models\Admin;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Seller;
use App\Models\GlobalSetting;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class AdminDashboardController extends Controller
{
    /**
     * Show the admin dashboard.
     */
    public function index()
    {
        // 1. Gather general statistics
        $totalClients = Organization::count();
        $activeClients = Organization::where('status', 'active')->count();
        $totalStores = Store::count();
        $totalUsers = User::count();

        // 2. Fetch all system modules with their organization count
        $modulesUsage = Module::withCount('organization')->get();

        // 3. Fetch all clients/organizations with their modules and usage statistics
        $organizations = Organization::with(['user', 'modules'])
            ->withCount(['invoices', 'credits', 'sellers'])
            ->withSum('invoices as total_invoiced', 'grand_total')
            ->withSum('credits as total_debt', 'debt')
            ->get();
        $allModules = Module::all();

        // 4. Fetch all platform administrators (root users)
        $admins = Admin::all();

        // 5. Fetch database backups
        $backups = [];
        $backupDir = storage_path('app/backups');
        if (file_exists($backupDir)) {
            $files = glob($backupDir . '/backup-*');
            if ($files) {
                usort($files, function($a, $b) {
                    return filemtime($b) - filemtime($a);
                });
                foreach ($files as $file) {
                    $name = basename($file);
                    $size = filesize($file);
                    if ($size >= 1048576) {
                        $sizeStr = number_format($size / 1048576, 2) . ' MB';
                    } elseif ($size >= 1024) {
                        $sizeStr = number_format($size / 1024, 2) . ' KB';
                    } else {
                        $sizeStr = $size . ' B';
                    }
                    $backups[] = [
                        'name' => $name,
                        'size' => $sizeStr,
                        'created_at' => date('d/m/Y H:i:s', filemtime($file)),
                    ];
                }
            }
        }

        return view('admin.dashboard', compact(
            'totalClients',
            'activeClients',
            'totalStores',
            'totalUsers',
            'modulesUsage',
            'organizations',
            'allModules',
            'admins',
            'backups'
        ));
    }

    /**
     * Show details of a client.
     */
    public function showClient($id)
    {
        $organization = Organization::with(['user', 'modules'])
            ->withCount(['invoices', 'credits', 'sellers'])
            ->withSum('invoices as total_invoiced', 'grand_total')
            ->withSum('credits as total_debt', 'debt')
            ->findOrFail($id);

        $allModules = Module::all();

        return view('admin.clients.show', compact('organization', 'allModules'));
    }

    /**
     * Toggle the status of a client (active/inactive).
     */
    public function toggleClientStatus($id)
    {
        $organization = Organization::findOrFail($id);
        $newStatus = $organization->status === 'active' ? 'inactive' : 'active';
        
        $organization->update(['status' => $newStatus]);

        return redirect()->back()
            ->with('success', "Estado de '{$organization->name}' actualizado a '{$newStatus}' correctamente.");
    }

    public function toggleClientModule($id, $moduleId, Request $request)
    {
        $organization = Organization::findOrFail($id);
        $module = $organization->modules()->where('module_id', $moduleId)->first();

        if ($module) {
            $newStatus = $module->pivot->status === 'active' ? 'inactive' : 'active';
            $organization->modules()->updateExistingPivot($moduleId, ['status' => $newStatus]);
            $msg = "Módulo '{$module->name}' para '{$organization->name}' marcado como {$newStatus}.";
        } else {
            $organization->modules()->attach($moduleId, ['status' => 'active']);
            $realModule = Module::find($moduleId);
            $msg = "Módulo '{$realModule->name}' asignado como activo a '{$organization->name}'.";
        }

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['success' => true, 'message' => $msg]);
        }

        return redirect()->route('admin.dashboard')->with('success', $msg);
    }

    public function updateLicense(Request $request, $id)
    {
        $request->validate([
            'type' => 'required|in:add,replace,lifetime,revoke',
            'days' => 'required_if:type,add,replace|nullable|integer|min:1',
        ]);

        $organization = Organization::findOrFail($id);
        $type = $request->type;
        $days = (int) $request->days;

        if ($type === 'lifetime') {
            $organization->update([
                'is_lifetime' => true,
                'license_expires_at' => null,
            ]);
            $organization->licenses()->create([
                'type' => 'lifetime',
                'days' => null,
            ]);
            return redirect()->back()->with('success', 'Licencia actualizada a de por vida.');
        }

        if ($type === 'revoke') {
            $organization->update([
                'is_lifetime' => false,
                'license_expires_at' => now(), // instantly expire it today
            ]);
            $organization->licenses()->create([
                'type' => 'revoke',
                'days' => 0,
            ]);
            return redirect()->back()->with('success', 'Licencia revocada (Vencida inmediatamente).');
        }

        $organization->is_lifetime = false;
        
        if ($type === 'replace') {
            $lastLicense = $organization->licenses()->whereIn('type', ['add', 'replace'])->latest()->first();
            if (!$lastLicense || !$lastLicense->created_at->isToday()) {
                return redirect()->back()->withErrors(['error' => 'Solo puedes reemplazar los días si la última compra se realizó el día de hoy.']);
            }
            
            // Revert to previous_expires_at
            $baseDate = $lastLicense->previous_expires_at ? \Carbon\Carbon::parse($lastLicense->previous_expires_at) : now();
            if ($baseDate->isPast()) {
                $baseDate = now();
            }
            $newExpiresAt = $baseDate->addDays($days);

            $organization->licenses()->create([
                'type' => 'replace',
                'days' => $days,
                'previous_expires_at' => $lastLicense->previous_expires_at,
                'new_expires_at' => $newExpiresAt,
            ]);

            $organization->update(['license_expires_at' => $newExpiresAt]);
            return redirect()->back()->with('success', "Días reemplazados correctamente. Nueva expiración: {$newExpiresAt->format('d/m/Y')}.");
        }

        // Action: ADD
        $baseDate = ($organization->license_expires_at && $organization->license_expires_at->isFuture()) 
                    ? $organization->license_expires_at 
                    : now();
        $previousExpiresAt = $organization->license_expires_at;
        $newExpiresAt = $baseDate->copy()->addDays($days);

        $organization->licenses()->create([
            'type' => 'add',
            'days' => $days,
            'previous_expires_at' => $previousExpiresAt,
            'new_expires_at' => $newExpiresAt,
        ]);

        $organization->update(['license_expires_at' => $newExpiresAt]);
        return redirect()->back()->with('success', "Días agregados correctamente. Nueva expiración: {$newExpiresAt->format('d/m/Y')}.");
    }

    public function globalSettings()
    {
        $supportMessage = GlobalSetting::where('key', 'license_support_message')->value('value') ?? '';
        return view('admin.settings.index', compact('supportMessage'));
    }

    public function updateGlobalSettings(Request $request)
    {
        $request->validate(['license_support_message' => 'nullable|string']);
        
        GlobalSetting::updateOrCreate(
            ['key' => 'license_support_message'],
            ['value' => $request->license_support_message]
        );

        return redirect()->back()->with('success', 'Configuración global actualizada correctamente.');
    }

    /**
     * Store a new organization (client) and its owner.
     */
    public function storeClient(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:organizations,email',
            'phone' => 'required|string|max:255',
            'owner_name' => 'required|string|max:255',
            'owner_email' => 'required|email|unique:users,email',
            'owner_password' => 'required|string|min:8',
        ]);

        $originalConnection = DB::getDefaultConnection();
        DB::setDefaultConnection('central');
        DB::connection('central')->beginTransaction();
        try {
            // 1. Create owner user first
            $user = User::create([
                'name' => $request->owner_name,
                'email' => $request->owner_email,
                'password' => Hash::make($request->owner_password),
                'status' => 'active',
            ]);

            // 2. Create organization
            $organization = Organization::create([
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'status' => 'active',
                'owner_id' => $user->id,
                'tenancy_type' => 'shared',
            ]);

            // 3. Link user to organization early so role assignment has a valid organization_id
            $user->update(['organization_id' => $organization->id]);

            // 4. Associate all active modules
            $modules = Module::where('status', 'active')->get();
            foreach ($modules as $mod) {
                $organization->modules()->syncWithoutDetaching([$mod->id => ['status' => 'active']]);
            }

            // 5. Create Owner Role and sync permissions
            $allPermissions = Permission::all();
            $ownerRole = Role::firstOrCreate(
                ['name' => 'Owner', 'organization_id' => $organization->id],
                ['guard_name' => 'web']
            );
            $ownerRole->syncPermissions($allPermissions);

            // 6. Create default Manager and Seller roles
            $sellerPermissions = [
                'invoice.index', 'invoice.show', 'invoice.store',
                'client.index', 'client.show', 'client.store', 'client.update',
                'product.index', 'product.show',
                'inventory.index', 'inventory.show',
                'credit.index', 'credit.show', 'credit.payment',
                'seller.index', 'seller.show',
            ];
            $managerPermissions = array_merge($sellerPermissions, [
                'product.store', 'product.update',
                'inventory.store', 'inventory.update',
                'supplier.index', 'supplier.show', 'supplier.store', 'supplier.update',
                'purchase.index', 'purchase.show', 'purchase.store',
                'report.index', 'report.store', 'report.download',
            ]);

            $managerRole = Role::firstOrCreate(
                ['name' => 'Manager', 'organization_id' => $organization->id],
                ['guard_name' => 'web']
            );
            $managerRole->syncPermissions($managerPermissions);

            $sellerRole = Role::firstOrCreate(
                ['name' => 'Seller', 'organization_id' => $organization->id],
                ['guard_name' => 'web']
            );
            $sellerRole->syncPermissions($sellerPermissions);

            // 7. Assign Owner role and update role_id
            $user->assignRole($ownerRole);
            $user->update(['role_id' => $ownerRole->uuid]);

            // 8. Create owner seller profile and link to user
            $seller = Seller::withoutGlobalScopes()->create([
                'organization_id' => $organization->id,
                'name'            => $user->name,
                'code'            => 'OWNER-' . strtoupper(substr($user->id, 0, 6)),
                'status'          => 'active',
                'is_owner'        => true,
                'pin_hash'        => Hash::make('1234'),
            ]);
            $user->update(['seller_id' => $seller->id]);

            event(new \App\Events\UserCreated($user));

            DB::connection('central')->commit();

            return redirect()->route('admin.dashboard', ['tab' => 'clients'])
                ->with('success', "Cliente '{$organization->name}' y su usuario propietario creados con éxito.");

        } catch (\Throwable $e) {
            DB::connection('central')->rollBack();
            \Illuminate\Support\Facades\Log::error('storeClient failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return redirect()->route('admin.dashboard', ['tab' => 'clients'])
                ->withErrors(['error' => 'Error al crear el cliente: ' . $e->getMessage()]);
        } finally {
            DB::setDefaultConnection($originalConnection);
        }
    }

    /**
     * Store a new platform administrator.
     */
    public function storeAdmin(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:admins,email',
            'password' => 'required|string|min:8',
        ]);

        Admin::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'status' => 'active',
        ]);

        return redirect()->route('admin.dashboard', ['tab' => 'admins'])
            ->with('success', "Administrador '{$request->name}' creado con éxito.");
    }

    /**
     * Generate a new database backup.
     */
    public function generateBackup()
    {
        try {
            \Illuminate\Support\Facades\Artisan::call('db:backup', ['--compress' => true]);
            $output = \Illuminate\Support\Facades\Artisan::output();
            
            if (str_contains($output, 'error') || str_contains($output, 'Fallo')) {
                return redirect()->route('admin.dashboard', ['tab' => 'backups'])
                    ->withErrors(['error' => 'Error al generar la copia de seguridad: ' . $output]);
            }

            return redirect()->route('admin.dashboard', ['tab' => 'backups'])
                ->with('success', 'Copia de seguridad de la base de datos generada correctamente.');
        } catch (\Exception $e) {
            return redirect()->route('admin.dashboard', ['tab' => 'backups'])
                ->withErrors(['error' => 'Error al ejecutar comando de backup: ' . $e->getMessage()]);
        }
    }

    /**
     * Download a backup file.
     */
    public function downloadBackup($filename)
    {
        // Security check: avoid path traversal
        if (str_contains($filename, '..') || str_contains($filename, '/') || str_contains($filename, '\\')) {
            abort(403, 'Acceso denegado.');
        }

        $filePath = storage_path('app/backups/' . $filename);

        if (!file_exists($filePath)) {
            abort(404, 'El archivo solicitado no existe.');
        }

        return response()->download($filePath);
    }

    /**
     * Hard-delete an organization and ALL its related data.
     * Requires the admin to confirm their own password.
     */
    public function destroyClient(Request $request, $id)
    {
        $request->validate([
            'admin_password' => 'required|string',
        ]);

        // Verify the currently authenticated admin's password
        $admin = Auth::guard('admin')->user();
        if (!Hash::check($request->admin_password, $admin->password)) {
            return redirect()->back()
                ->withErrors(['admin_password' => 'La contraseña ingresada es incorrecta.'])
                ->withInput();
        }

        $organization = Organization::findOrFail($id);
        $orgId   = $organization->id;
        $orgName = $organization->name;

        $central = DB::connection('central');

        $central->beginTransaction();
        try {
            // Disable FK checks to avoid topological sorting issues with circular constraints
            \Illuminate\Support\Facades\Schema::connection('central')->disableForeignKeyConstraints();

            // ── STEP 1: Clean Spatie permission pivots ────────────────────────
            $roleIds = $central->table('roles')
                ->where('organization_id', $orgId)
                ->pluck('uuid');

            if ($roleIds->isNotEmpty()) {
                $central->table('model_has_roles')
                    ->whereIn('role_id', $roleIds)
                    ->delete();

                $central->table('role_has_permissions')
                    ->whereIn('role_id', $roleIds)
                    ->delete();
            }

            // ── STEP 2: Delete roles ──────────────────────────────────────────
            $central->table('roles')
                ->where('organization_id', $orgId)
                ->delete();

            // ── STEP 3: Capture user IDs BEFORE nullifying any FK ─────────────
            $userIds = $central->table('users')
                ->where('organization_id', $orgId)
                ->pluck('id');

            // ── STEP 4: Nullify self-referential FKs on users ─────────────────
            if ($userIds->isNotEmpty()) {
                $central->table('users')
                    ->whereIn('id', $userIds)
                    ->update(['seller_id' => null, 'role_id' => null, 'organization_id' => null]);
            }

            // ── STEP 5: Delete sellers ────────────────────────────────────────
            $central->table('sellers')
                ->where('organization_id', $orgId)
                ->delete();

            // ── STEP 6: Delete business data ──────────────────────────────────
            $central->table('invoices')->where('organization_id', $orgId)->delete();

            try {
                $central->table('credit_details')->where('organization_id', $orgId)->delete();
            } catch (\Throwable $e) {}

            $central->table('credits')->where('organization_id', $orgId)->delete();
            $central->table('clients')->where('organization_id', $orgId)->delete();
            $central->table('stores')->where('organization_id', $orgId)->delete();

            // ── STEP 7: Detach module pivot ───────────────────────────────────
            $central->table('organization_modules')
                ->where('organization_id', $orgId)
                ->delete();

            // ── STEP 8: Delete users ──────────────────────────────────────────
            if ($userIds->isNotEmpty()) {
                $central->table('users')
                    ->whereIn('id', $userIds)
                    ->delete();
            }

            // ── STEP 9: Delete the organization ───────────────────────────────
            $central->table('organizations')->where('id', $orgId)->delete();

            $central->commit();
            \Illuminate\Support\Facades\Schema::connection('central')->enableForeignKeyConstraints();

            return redirect()->route('admin.dashboard', ['tab' => 'clients'])
                ->with('success', "La organización '{$orgName}' y todos sus datos han sido eliminados permanentemente.");

        } catch (\Throwable $e) {
            $central->rollBack();
            \Illuminate\Support\Facades\Schema::connection('central')->enableForeignKeyConstraints();
            \Illuminate\Support\Facades\Log::error('destroyClient failed', [
                'org_id' => $orgId,
                'error'  => $e->getMessage(),
                'trace'  => $e->getTraceAsString(),
            ]);
            return redirect()->back()
                ->with('delete_error', 'Error al eliminar la organización: ' . $e->getMessage());
        }
    }




    /**
     * Delete a backup file.
     */
    public function deleteBackup($filename)
    {
        // Security check: avoid path traversal
        if (str_contains($filename, '..') || str_contains($filename, '/') || str_contains($filename, '\\')) {
            abort(403, 'Acceso denegado.');
        }

        $filePath = storage_path('app/backups/' . $filename);

        if (file_exists($filePath)) {
            unlink($filePath);
            return redirect()->route('admin.dashboard', ['tab' => 'backups'])
                ->with('success', 'Copia de seguridad eliminada con éxito.');
        }

        return redirect()->route('admin.dashboard', ['tab' => 'backups'])
            ->withErrors(['error' => 'El archivo no existe o ya fue eliminado.']);
    }
}
