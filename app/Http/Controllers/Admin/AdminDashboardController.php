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
        $organizations = Organization::with(['user', 'modules', 'plan'])
            ->withCount(['invoices', 'credits', 'sellers'])
            ->withSum('invoices as total_invoiced', 'grand_total')
            ->withSum('credits as total_debt', 'debt')
            ->get();
        $allModules = Module::all();

        // 4. Fetch all platform administrators (root users)
        $admins = Admin::all();

        // 5. Fetch database backups (spatie/laravel-backup)
        $backups = $this->listBackups();

        // 6. Métricas de negocio
        $business = $this->businessMetrics();

        return view('admin.dashboard', compact(
            'totalClients',
            'activeClients',
            'totalStores',
            'totalUsers',
            'modulesUsage',
            'organizations',
            'allModules',
            'admins',
            'backups',
            'business'
        ));
    }

    /**
     * Métricas de negocio para el dashboard: MRR, licencias por vencer,
     * activas vs vencidas, distribución por plan/tenencia, facturación por mes,
     * cajas abiertas y ranking de organizaciones por actividad.
     */
    private function businessMetrics(): array
    {
        $now = now();

        // MRR: suma de (price / duration_months) de orgs con plan vigente (no vencidas).
        $activeWithPlan = Organization::with('plan')
            ->whereNotNull('plan_id')
            ->where(function ($q) use ($now) {
                $q->where('is_lifetime', true)
                  ->orWhere('license_expires_at', '>=', $now);
            })
            ->get();

        $mrr = $activeWithPlan->reduce(function ($carry, $org) {
            if (!$org->plan || $org->plan->duration_months < 1) {
                return $carry;
            }
            return $carry + ($org->plan->price / $org->plan->duration_months);
        }, 0.0);

        // Licencias por vencer (7 / 15 / 30 días) y estado activo/vencido.
        $notLifetime = Organization::where('is_lifetime', false)->whereNotNull('license_expires_at');
        $expiring7 = (clone $notLifetime)->whereBetween('license_expires_at', [$now, (clone $now)->addDays(7)])->count();
        $expiring15 = (clone $notLifetime)->whereBetween('license_expires_at', [$now, (clone $now)->addDays(15)])->count();
        $expiring30 = (clone $notLifetime)->whereBetween('license_expires_at', [$now, (clone $now)->addDays(30)])->count();

        $licensedActive = Organization::where(function ($q) use ($now) {
            $q->where('is_lifetime', true)->orWhere('license_expires_at', '>=', $now);
        })->count();
        $licensedExpired = Organization::where('is_lifetime', false)
            ->where(function ($q) use ($now) {
                $q->whereNull('license_expires_at')->orWhere('license_expires_at', '<', $now);
            })->count();

        // Distribución por tenencia.
        $sharedCount = Organization::where('tenancy_type', 'shared')->count();
        $dedicatedCount = Organization::where('tenancy_type', 'dedicated')->count();

        // Distribución por plan.
        $planDistribution = Organization::selectRaw('plan_id, count(*) as total')
            ->whereNotNull('plan_id')
            ->groupBy('plan_id')
            ->with('plan')
            ->get()
            ->map(fn ($row) => [
                'name' => $row->plan?->name ?? 'N/A',
                'total' => $row->total,
            ]);
        $noPlanCount = Organization::whereNull('plan_id')->count();

        // Facturación de los últimos 6 meses (todas las orgs).
        $invoicesByMonth = \App\Models\Invoice::selectRaw("DATE_FORMAT(created_at, '%Y-%m') as ym, count(*) as total, sum(grand_total) as amount")
            ->where('created_at', '>=', (clone $now)->subMonths(6)->startOfMonth())
            ->groupBy('ym')
            ->orderBy('ym')
            ->get();

        // Cajas abiertas actualmente.
        $openCashSessions = \App\Models\CashSession::where('status', 'open')->count();

        // Ranking de organizaciones por facturas del mes actual.
        $topOrgs = \App\Models\Invoice::selectRaw('organization_id, count(*) as total, sum(grand_total) as amount')
            ->whereBetween('created_at', [(clone $now)->startOfMonth(), (clone $now)->endOfMonth()])
            ->groupBy('organization_id')
            ->orderByDesc('total')
            ->limit(5)
            ->get()
            ->map(function ($row) {
                $org = Organization::find($row->organization_id);
                return [
                    'name' => $org?->name ?? '—',
                    'invoices' => $row->total,
                    'amount' => (float) $row->amount,
                ];
            });

        return [
            'mrr' => round($mrr, 2),
            'expiring7' => $expiring7,
            'expiring15' => $expiring15,
            'expiring30' => $expiring30,
            'licensedActive' => $licensedActive,
            'licensedExpired' => $licensedExpired,
            'sharedCount' => $sharedCount,
            'dedicatedCount' => $dedicatedCount,
            'planDistribution' => $planDistribution,
            'noPlanCount' => $noPlanCount,
            'invoicesByMonth' => $invoicesByMonth,
            'openCashSessions' => $openCashSessions,
            'topOrgs' => $topOrgs,
        ];
    }

    /**
     * Show details of a client.
     */
    public function showClient($id)
    {
        $organization = Organization::with(['user', 'modules', 'plan'])
            ->withCount(['invoices', 'credits', 'sellers'])
            ->withSum('invoices as total_invoiced', 'grand_total')
            ->withSum('credits as total_debt', 'debt')
            ->findOrFail($id);

        $allModules = Module::all();
        $plans = \App\Models\Plan::where('is_active', true)->orderBy('price')->get();

        return view('admin.clients.show', compact('organization', 'allModules', 'plans'));
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

        \App\Services\AdminAudit::log('license.' . $type, 'organization', $organization->id, "Licencia '{$type}'" . ($days ? " ({$days} días)" : '') . " en {$organization->name}");

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

    /**
     * Asigna un plan a la organización: fija el plan y el tipo de tenencia, y
     * extiende la licencia por la duración del plan (en meses).
     */
    public function assignPlan(Request $request, $id)
    {
        $request->validate([
            'plan_id' => 'required|uuid|exists:central.plans,id',
        ]);

        $organization = Organization::findOrFail($id);
        $plan = \App\Models\Plan::findOrFail($request->plan_id);

        $baseDate = ($organization->license_expires_at && $organization->license_expires_at->isFuture())
            ? $organization->license_expires_at
            : now();
        $previousExpiresAt = $organization->license_expires_at;
        $newExpiresAt = $baseDate->copy()->addMonths($plan->duration_months);

        $organization->licenses()->create([
            'type' => 'add',
            'days' => $plan->duration_months * 30,
            'previous_expires_at' => $previousExpiresAt,
            'new_expires_at' => $newExpiresAt,
        ]);

        $organization->update([
            'plan_id' => $plan->id,
            'tenancy_type' => $plan->tenancy_type,
            'is_lifetime' => false,
            'license_expires_at' => $newExpiresAt,
        ]);

        \App\Services\AdminAudit::log('plan.assign', 'organization', $organization->id, "Plan '{$plan->name}' asignado a {$organization->name}");

        return redirect()->back()->with('success', "Plan '{$plan->name}' asignado. Vence: {$newExpiresAt->format('d/m/Y')}.");
    }

    public function auditLog()
    {
        $logs = \App\Models\AdminAuditLog::orderByDesc('created_at')->paginate(50);
        return view('admin.audit.index', compact('logs'));
    }

    public function globalSettings()
    {
        $supportMessage = GlobalSetting::where('key', 'license_support_message')->value('value') ?? '';
        $googleClientId = GlobalSetting::where('key', 'google_client_id')->value('value') ?? '';
        $googleClientSecret = GlobalSetting::where('key', 'google_client_secret')->value('value') ?? '';
        $googleRedirectUri = GlobalSetting::where('key', 'google_redirect_uri')->value('value') ?? '';
        // Datos del emisor para las facturas de suscripción.
        $company = [
            'company_legal_name' => GlobalSetting::where('key', 'company_legal_name')->value('value') ?? 'DipleBill',
            'company_ruc'        => GlobalSetting::where('key', 'company_ruc')->value('value') ?? '',
            'company_address'    => GlobalSetting::where('key', 'company_address')->value('value') ?? '',
            'company_phone'      => GlobalSetting::where('key', 'company_phone')->value('value') ?? '',
            'company_email'      => GlobalSetting::where('key', 'company_email')->value('value') ?? '',
            'company_website'    => GlobalSetting::where('key', 'company_website')->value('value') ?? '',
        ];

        return view('admin.settings.index', compact(
            'supportMessage',
            'googleClientId',
            'googleClientSecret',
            'googleRedirectUri',
            'company'
        ));
    }

    public function updateGlobalSettings(Request $request)
    {
        $request->validate([
            'license_support_message' => 'nullable|string',
            'google_client_id' => 'nullable|string',
            'google_client_secret' => 'nullable|string',
            'google_redirect_uri' => 'nullable|string',
            'company_legal_name' => 'nullable|string',
            'company_ruc' => 'nullable|string',
            'company_address' => 'nullable|string',
            'company_phone' => 'nullable|string',
            'company_email' => 'nullable|email',
            'company_website' => 'nullable|string',
        ]);

        $keys = [
            'license_support_message',
            'google_client_id',
            'google_client_secret',
            'google_redirect_uri',
            'company_legal_name',
            'company_ruc',
            'company_address',
            'company_phone',
            'company_email',
            'company_website',
        ];

        foreach ($keys as $key) {
            GlobalSetting::updateOrCreate(['key' => $key], ['value' => $request->input($key)]);
        }

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
                'must_change_password' => true,
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

            // 8. Create owner seller profile and link to user.
            // PIN aleatorio (antes era '1234' fijo): se muestra una sola vez al admin
            // para que lo comunique al cliente; en BD queda hasheado.
            $ownerPin = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
            $seller = Seller::withoutGlobalScopes()->create([
                'organization_id' => $organization->id,
                'name'            => $user->name,
                'code'            => 'OWNER-' . strtoupper(substr($user->id, 0, 6)),
                'status'          => 'active',
                'is_owner'        => true,
                'pin_hash'        => Hash::make($ownerPin),
            ]);
            $user->update(['seller_id' => $seller->id]);

            event(new \App\Events\UserCreated($user));

            DB::connection('central')->commit();

            \App\Services\AdminAudit::log('client.create', 'organization', $organization->id, "Cliente {$organization->name} creado");

            return redirect()->route('admin.dashboard', ['tab' => 'clients'])
                ->with('success', "Cliente '{$organization->name}' creado. PIN del vendedor propietario: {$ownerPin} (anótalo, no se volverá a mostrar).");

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
    // ---- Backups (spatie/laravel-backup) ----

    private function backupDisk(): string
    {
        $disks = config('backup.backup.destination.disks', ['local']);
        return $disks[0] ?? 'local';
    }

    private function backupDir(): string
    {
        return config('backup.backup.name', 'DipleBill');
    }

    private function listBackups(): array
    {
        // Listar backups nunca debe tumbar el dashboard: si el disco no se puede
        // leer (permisos), la carpeta no existe aún, o el disco (p.ej. s3) está
        // mal configurado, degradamos a lista vacía en lugar de lanzar un 500.
        try {
            $disk = \Illuminate\Support\Facades\Storage::disk($this->backupDisk());
            $dir = $this->backupDir();

            if (!$disk->exists($dir)) {
                return [];
            }

            $files = collect($disk->files($dir))
                ->filter(fn ($f) => str_ends_with($f, '.zip'))
                ->sortByDesc(fn ($f) => $disk->lastModified($f))
                ->values();

            return $files->map(function ($f) use ($disk) {
                $size = $disk->size($f);
                if ($size >= 1048576) {
                    $sizeStr = number_format($size / 1048576, 2) . ' MB';
                } elseif ($size >= 1024) {
                    $sizeStr = number_format($size / 1024, 2) . ' KB';
                } else {
                    $sizeStr = $size . ' B';
                }
                return [
                    'name' => basename($f),
                    'size' => $sizeStr,
                    'created_at' => date('d/m/Y H:i:s', $disk->lastModified($f)),
                ];
            })->all();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('No se pudieron listar los backups: ' . $e->getMessage());

            return [];
        }
    }

    public function generateBackup()
    {
        // El dump + zip de una base grande tarda minutos: correrlo dentro del
        // request agota el timeout del proxy/nginx (~60s) y devuelve 504 aunque
        // el backup siga bien. Se lanza en SEGUNDO PLANO (proceso detach, como
        // www-data) y el request responde al instante; el zip aparece en la
        // lista al terminar. El backup diario ya corre por el scheduler (CLI).
        try {
            $cmd = sprintf(
                'nohup php %s backup:run --only-db < /dev/null >> %s 2>&1 &',
                escapeshellarg(base_path('artisan')),
                escapeshellarg(storage_path('logs/backup.log'))
            );

            \Symfony\Component\Process\Process::fromShellCommandline($cmd, base_path())
                ->setTimeout(15)
                ->run();

            return redirect()->route('admin.dashboard', ['tab' => 'backups'])
                ->with('success', 'Copia de seguridad iniciada en segundo plano. Aparecerá en la lista en 1–2 minutos según el tamaño de la base; refresca para verla.');
        } catch (\Throwable $e) {
            return redirect()->route('admin.dashboard', ['tab' => 'backups'])
                ->withErrors(['error' => 'No se pudo iniciar el backup: ' . $e->getMessage()]);
        }
    }

    public function downloadBackup($filename)
    {
        if (str_contains($filename, '..') || str_contains($filename, '/') || str_contains($filename, '\\')) {
            abort(403, 'Acceso denegado.');
        }

        $disk = \Illuminate\Support\Facades\Storage::disk($this->backupDisk());
        $path = $this->backupDir() . '/' . $filename;

        if (!$disk->exists($path)) {
            abort(404, 'El archivo solicitado no existe.');
        }

        return $disk->download($path);
    }

    /**
     * Elimina una organización. Por defecto hace SOFT-DELETE (reversible): marca
     * la org como eliminada y la suspende, conservando sus datos. Solo con el
     * flag `permanent` hace el borrado definitivo en cascada (irreversible).
     * En ambos casos exige confirmar la contraseña del admin.
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

        // Soft-delete (por defecto): reversible, no destruye datos.
        if (!$request->boolean('permanent')) {
            $organization->update(['status' => 'inactive']);
            $organization->delete(); // marca deleted_at (SoftDeletes)
            \App\Services\AdminAudit::log('client.soft_delete', 'organization', $orgId, "Organización {$orgName} archivada (soft-delete)");

            return redirect()->route('admin.dashboard', ['tab' => 'clients'])
                ->with('success', "La organización '{$orgName}' fue archivada. Sus datos se conservan y puede restaurarse.");
        }

        \App\Services\AdminAudit::log('client.hard_delete', 'organization', $orgId, "Organización {$orgName} ELIMINADA permanentemente");

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

        $disk = \Illuminate\Support\Facades\Storage::disk($this->backupDisk());
        $path = $this->backupDir() . '/' . $filename;

        if ($disk->exists($path)) {
            $disk->delete($path);
            return redirect()->route('admin.dashboard', ['tab' => 'backups'])
                ->with('success', 'Copia de seguridad eliminada con éxito.');
        }

        return redirect()->route('admin.dashboard', ['tab' => 'backups'])
            ->withErrors(['error' => 'El archivo no existe o ya fue eliminado.']);
    }
}
