<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMovementRequest;
use App\Http\Resources\MovementCollection;
use App\Http\Resources\MovementResource;
use App\Models\InventoryMovement;
use App\Services\InventoryMovementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Exception;

class MovementController extends Controller
{
    use \Illuminate\Foundation\Auth\Access\AuthorizesRequests;

    protected InventoryMovementService $movementService;

    public function __construct(InventoryMovementService $movementService)
    {
        $this->movementService = $movementService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, $inventoryId = null)
    {
        $this->authorize('viewAny', InventoryMovement::class);

        $orgId = Auth::user()->organization_id;
        $query = InventoryMovement::where('organization_id', $orgId)
            ->with(['product', 'user', 'seller']);

        // Check if inventory_id is passed as parameter or query param
        $inventory = $inventoryId ?? $request->inventory_id;
        if ($inventory) {
            $query->where('inventory_id', $inventory);
        }

        if ($request->product_id) {
            $query->where('product_id', $request->product_id);
        }

        if ($request->type) {
            $query->where('type', $request->type);
        }

        if ($request->direction) {
            $query->where('direction', $request->direction);
        }

        if ($request->date_from) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->date_to) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        if ($request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->whereHas('product', function($pq) use ($search) {
                    $pq->where('name', 'like', "%{$search}%")
                      ->orWhere('sku', 'like', "%{$search}%")
                      ->orWhere('barcode', 'like', "%{$search}%");
                });
            });
        }

        $query->orderBy('created_at', 'desc');

        $movements = $query->paginate($request->per_page ?? 15);
        return new MovementCollection($movements);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreMovementRequest $request)
    {
        $this->authorize('create', InventoryMovement::class);

        try {
            $movement = $this->movementService->recordMovement($request->validated());
            return response()->json([
                'message' => 'Movimiento de inventario registrado con éxito',
                'data' => new MovementResource($movement)
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $movement = InventoryMovement::findOrFail($id);
        $this->authorize('view', $movement);

        return new MovementResource($movement);
    }

    /**
     * Remove (reverse) the specified resource from storage.
     */
    public function destroy($id)
    {
        $movement = InventoryMovement::findOrFail($id);
        $this->authorize('delete', $movement);

        try {
            $reversal = $this->movementService->reverseMovement($movement);
            return response()->json([
                'message' => 'Movimiento reversado con éxito',
                'data' => new MovementResource($reversal)
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        }
    }
}
