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
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

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

        DB::beginTransaction();
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

            // 3. Associate all active modules
            $modules = Module::where('status', 'active')->get();
            foreach ($modules as $mod) {
                $organization->modules()->syncWithoutDetaching([$mod->id => ['status' => 'active']]);
            }

            // 4. Create Owner Role and sync permissions
            $allPermissions = Permission::all();
            $ownerRole = Role::firstOrCreate(
                ['name' => 'Owner', 'organization_id' => $organization->id],
                ['guard_name' => 'web']
            );
            $ownerRole->syncPermissions($allPermissions);

            // 5. Create default Manager and Seller roles
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

            // 6. Link user to organization and assign role
            $user->update([
                'organization_id' => $organization->id,
                'role_id'         => $ownerRole->uuid,
            ]);
            $user->assignRole($ownerRole);

            // 7. Create owner seller profile
            $seller = Seller::create([
                'organization_id' => $organization->id,
                'name'            => $user->name,
                'code'            => 'OWNER-' . strtoupper(substr($user->id, 0, 6)),
                'status'          => 'active',
                'is_owner'        => true,
                'pin_hash'        => Hash::make('1234'),
            ]);
            $user->update(['seller_id' => $seller->id]);

            DB::commit();

            return redirect()->route('admin.dashboard', ['tab' => 'clients'])
                ->with('success', "Cliente '{$organization->name}' y su usuario propietario creados con éxito.");

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->route('admin.dashboard', ['tab' => 'clients'])
                ->withErrors(['error' => 'Error al crear el cliente: ' . $e->getMessage()]);
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
