<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Seller;
use Illuminate\Http\Request;
use App\Http\Requests\StoreSellerRequest;
use App\Http\Requests\UpdateSellerRequest;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

use App\Http\Resources\SellerResource;
use App\Http\Resources\SellerCollection;

class SellerController extends Controller
{
    /**
     * Listado con filtros por status y store(s).
     */
    public function index(Request $request)
    {
        $orgId = Auth::user()->organization_id;

        $query = Seller::with(['stores' => function ($q) {
                $q->select('stores.id', 'stores.name');
            }])
            ->where('organization_id', $orgId);

        // Filtro por status (active/inactive/blocked)
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        // Filtro por store_id (acepta uno o varios)
        if ($request->filled('store_id')) {
            $storeIds = (array) $request->input('store_id');
            $query->whereHas('stores', function ($q) use ($storeIds) {
                $q->whereIn('stores.id', $storeIds);
            });
        }

        $sellers = $query->get();

        return new SellerCollection($sellers);
    }

    /**
     * Crear seller (hashea PIN y adjunta stores al pivote).
     */
    public function store(StoreSellerRequest $request)
    {
        $orgId     = Auth::user()->organization_id;
        $validated = $request->validated();

        // Unicidad del code dentro de la organización
        $exists = Seller::where('organization_id', $orgId)
            ->where('code', $validated['code'])
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'Seller with this code already exists',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $seller = Seller::create([
            'organization_id' => $orgId,
            'name'            => $validated['name'],
            'code'            => $validated['code'],
            'status'          => $validated['status'] ?? 'active',
            'pin_hash'        => Hash::make($validated['pin']),
        ]);

        // Adjuntar stores al pivote (status=active, assigned_at=now)
        if (!empty($validated['stores'])) {
            $attachPayload = [];
            foreach ($validated['stores'] as $storeId) {
                $attachPayload[$storeId] = [
                    'organization_id' => $orgId,
                    'status'          => 'active',
                    'assigned_at'     => now(),
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ];
            }
            $seller->stores()->attach($attachPayload);
        }

        return (new SellerResource($seller->load('stores')))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Mostrar un seller.
     */
    public function show(Seller $seller)
    {
        // Asegurar que pertenece a la org del usuario
        $this->authorizeSeller($seller);

        return new SellerResource($seller->load('stores'));
    }

    /**
     * Actualizar seller.
     * - Si viene 'pin': re-hash.
     * - Si viene 'stores': sync del pivote (manteniendo organization_id en el pivote).
     */
    public function update(UpdateSellerRequest $request, Seller $seller)
    {
        $this->authorizeSeller($seller);

        $orgId     = Auth::user()->organization_id;
        $validated = $request->validated();

        // Validar unicidad de 'code' dentro de la org (si viene)
        if (isset($validated['code'])) {
            $exists = Seller::where('organization_id', $orgId)
                ->where('code', $validated['code'])
                ->where('id', '!=', $seller->id)
                ->exists();

            if ($exists) {
                return response()->json([
                    'message' => 'Seller with this code already exists',
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        // Campos base
        $seller->fill([
            'name'   => $validated['name']   ?? $seller->name,
            'code'   => $validated['code']   ?? $seller->code,
            'status' => $validated['status'] ?? $seller->status,
        ]);

        // Actualizar PIN si viene
        if (array_key_exists('pin', $validated) && !is_null($validated['pin'])) {
            $seller->pin_hash = Hash::make($validated['pin']);
        }

        $seller->save();

        // Sync de stores si viene el array
        if (array_key_exists('stores', $validated)) {
            // Construir payload para el pivote
            $syncPayload = [];
            foreach ((array) $validated['stores'] as $storeId) {
                $syncPayload[$storeId] = [
                    'organization_id' => $orgId,
                    'status'          => 'active',
                    'assigned_at'     => now(),
                ];
            }
            // sync() reemplaza las asignaciones; si querés solo agregar, usar syncWithoutDetaching().
            $seller->stores()->sync($syncPayload);
        }

        return new SellerResource($seller->load('stores'));
    }

    /**
     * Eliminar seller (soft delete).
     */
    public function destroy(Seller $seller)
    {
        $this->authorizeSeller($seller);

        $seller->delete();

        return response()->noContent();
    }

    /**
     * Asignar uno o varios stores a un seller ya creado (sin desconectar los existentes).
     * Body esperado:
     * {
     *   "stores": ["uuid-store-1", "uuid-store-2"]
     * }
     */
    public function assignStores(Request $request, Seller $seller)
    {
        $this->authorizeSeller($seller);

        $validated = $request->validate([
            'stores'   => ['required', 'array', 'min:1'],
            'stores.*' => ['uuid', 'exists:stores,id'],
        ]);

        $orgId = Auth::user()->organization_id;

        $attachPayload = [];
        foreach ($validated['stores'] as $storeId) {
            $attachPayload[$storeId] = [
                'organization_id' => $orgId,
                'status'          => 'active',
                'assigned_at'     => now(),
                'created_at'      => now(),
                'updated_at'      => now(),
            ];
        }

        // Agrega sin quitar las que ya están
        $seller->stores()->syncWithoutDetaching($attachPayload);

        return new SellerResource($seller->load('stores'));
    }

    /**
     * Asegura que el seller pertenece a la organización del usuario autenticado.
     */
    protected function authorizeSeller(Seller $seller): void
    {
        $orgId = Auth::user()->organization_id;
        abort_if($seller->organization_id !== $orgId, Response::HTTP_FORBIDDEN, 'Forbidden');
    }

    public function removeStores(Request $request, Seller $seller)
    {
        $this->authorizeSeller($seller);

        $validated = $request->validate([
            'stores'   => ['required', 'array', 'min:1'],
            'stores.*' => ['uuid', 'exists:stores,id'],
        ]);

        $seller->stores()->detach($validated['stores']);

        return new SellerResource($seller->load('stores'));
    }

        public function sellerLogin(Request $request)
    {
        $orgId = Auth::user()->organization_id;

        $data = $request->validate([
            'store_id' => ['required', 'uuid', 'exists:stores,id'],
            'code'     => ['required', 'string', 'max:50'],
            'pin'      => ['required', 'string', 'min:4', 'max:10'],
        ]);

        // 1) Buscar seller por org + code y activo
        $seller = Seller::where('organization_id', $orgId)
            ->where('code', $data['code'])
            ->where('status', 'active')
            ->first();

        if (!$seller) {
            return response()->json([
                'message' => 'Invalid credentials',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // 2) Verificar PIN
        if (!Hash::check($data['pin'], $seller->pin_hash)) {
            return response()->json([
                'message' => 'Invalid credentials',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // 3) Verificar asignación del seller a esa store (pivote seller_store, status=active)
        $isAssigned = $seller->stores()
            ->where('stores.id', $data['store_id'])
            ->wherePivot('status', 'active')
            ->exists();

        if (!$isAssigned) {
            return response()->json([
                'message' => 'Seller not assigned to this store',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // 4) OK → devolver info mínima para el front
        return response()->json([
            'seller' => [
                'id'   => $seller->id,
                'name' => $seller->name,
                'code' => $seller->code,
            ],
            'store' => [
                'id' => $data['store_id'],
            ],
        ], Response::HTTP_OK);
    }
    public function generateOwnerSeller(Request $request)
    {
        $user = Auth::user();
        $orgId = $user->organization_id;

        DB::beginTransaction();

        try {
            // 1. Crear o recuperar el Seller del Owner
            if (!$user->seller_id) {
                // Verificar si ya existe un seller con este código (por si acaso se desincronizó)
                $code = 'OWNER-' . strtoupper(substr($user->id, 0, 6));
                
                $seller = Seller::where('organization_id', $orgId)
                    ->where('code', $code)
                    ->first();

                if (!$seller) {
                    $seller = Seller::create([
                        'organization_id' => $orgId,
                        'name'            => $user->name,
                        'code'            => $code,
                        'status'          => 'active',
                        'is_owner'        => true,
                        'pin_hash'        => Hash::make('1234'), // PIN por defecto
                    ]);
                }

                // Vincular al usuario
                $user->seller_id = $seller->id;
                $user->save();
            } else {
                $seller = Seller::find($user->seller_id);
            }

            // 2. Buscar todas las tiendas de la organización
            $stores = \App\Models\Store::where('organization_id', $orgId)->get();

            // 3. Asignar el seller a TODAS las tiendas
            $syncPayload = [];
            foreach ($stores as $store) {
                $syncPayload[$store->id] = [
                    'organization_id' => $orgId,
                    'status'          => 'active',
                    'assigned_at'     => now(),
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ];
            }

            // Usamos syncWithoutDetaching para asegurar que esté en todas sin borrar nada previo si hubiera
            if (!empty($syncPayload)) {
                $seller->stores()->syncWithoutDetaching($syncPayload);
            }

            DB::commit();

            return response()->json([
                'message' => 'Owner seller generated and assigned to all stores successfully',
                'seller'  => new SellerResource($seller->load('stores')),
            ], Response::HTTP_OK);

        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error generating owner seller',
                'error'   => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}