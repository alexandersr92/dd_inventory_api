<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDraftInvoiceRequest;
use App\Http\Requests\UpdateDraftInvoiceRequest;
use App\Http\Resources\InvoiceCollection;
use App\Http\Resources\InvoiceResource;
use App\Models\Client;
use App\Models\Credit;
use App\Models\InventoryDetail;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Store;
use App\Models\Setting;
use App\Services\InventoryMovementService;
use App\Services\PlanLimits;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class InvoiceDraftController extends Controller
{
    use AuthorizesRequests;

    /**
     * List all invoice drafts for the current organization.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Invoice::class);

        $orgId = Auth::user()->organization_id;
        $perPage = (int) $request->query('per_page', 20);
        $storeId = $request->query('store_id');
        $dateFrom = $request->query('date_from') ? $request->query('date_from') . ' 00:00:00' : null;
        $dateTo = $request->query('date_to') ? $request->query('date_to') . ' 23:59:59' : null;
        $clientName = $request->query('client_name');
        $sellerId = $request->query('seller_id');
        $search = $request->query('search');

        $query = Invoice::where('organization_id', $orgId)
            ->where('invoice_status', 'draft')
            ->with(['client', 'seller', 'invoiceDetails.product', 'store']);

        // SEGURIDAD: Restringir a las tiendas asignadas al usuario
        $allowedStoreIds = Auth::user()->allowedStoreIds();
        if ($allowedStoreIds !== null) {
            if ($storeId !== null && !in_array($storeId, $allowedStoreIds, true)) {
                return response()->json(['message' => 'No autorizado para esta tienda.'], Response::HTTP_FORBIDDEN);
            }
            $query->whereIn('store_id', $allowedStoreIds);
        }

        if ($storeId) {
            $query->where('store_id', $storeId);
        }

        if ($sellerId) {
            $query->where('seller_id', $sellerId);
        }

        if ($clientName) {
            $query->where('client_name', 'like', '%' . $clientName . '%');
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', '%' . $search . '%')
                    ->orWhere('client_name', 'like', '%' . $search . '%');
            });
        }

        if ($dateFrom && $dateTo) {
            $query->whereBetween('created_at', [$dateFrom, $dateTo]);
        } elseif ($dateFrom) {
            $query->where('created_at', '>=', $dateFrom);
        } elseif ($dateTo) {
            $query->where('created_at', '<=', $dateTo);
        }

        $sortBy = $request->query('sort_by', 'created_at');
        $order = $request->query('order', 'desc');
        $allowedSortFields = ['grand_total', 'created_at', 'client_name', 'invoice_number'];

        if (in_array($sortBy, $allowedSortFields, true)) {
            $query->orderBy($sortBy, $order);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $drafts = $query->paginate($perPage);

        return new InvoiceCollection($drafts);
    }

    /**
     * Store a new invoice draft without inventory or cash session movements.
     */
    public function store(StoreDraftInvoiceRequest $request)
    {
        $this->authorize('create', Invoice::class);

        $orgId = Auth::user()->organization_id;
        $userId = Auth::id();

        $allowDrafts = filter_var(
            Setting::where('organization_id', $orgId)->where('key', 'allow_invoice_drafts')->value('value'),
            FILTER_VALIDATE_BOOLEAN
        );
        if (!$allowDrafts) {
            return response()->json(['message' => 'La organización no tiene habilitada la gestión de borradores.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // SEGURIDAD: Verificar tienda permitida
        $allowedStoreIds = Auth::user()->allowedStoreIds();
        if ($allowedStoreIds !== null && !in_array($request->store_id, $allowedStoreIds, true)) {
            return response()->json(['message' => 'No autorizado para esta tienda.'], Response::HTTP_FORBIDDEN);
        }

        $store = Store::where('id', $request->store_id)
            ->where('organization_id', $orgId)
            ->firstOrFail();

        // Resolver inventario por defecto para la tienda
        $defaultInventoryId = $this->resolveDefaultInventoryId($orgId, $store->id, $request->inventory_id);

        $productArray = is_array($request->products)
            ? $request->products
            : json_decode($request->products, true, 512, JSON_THROW_ON_ERROR);

        $hasFreeItems = false;
        foreach ($productArray as $p) {
            if (($p['item_type'] ?? '') === 'free') {
                $hasFreeItems = true;
                break;
            }
        }
        if ($hasFreeItems) {
            $allowFree = filter_var(
                Setting::where('organization_id', $orgId)->where('key', 'allow_free_invoice_items')->value('value'),
                FILTER_VALIDATE_BOOLEAN
            );
            if (!$allowFree) {
                return response()->json(['message' => 'La organización no tiene habilitada la facturación de conceptos libres.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        $processed = $this->processProductItems($productArray, $orgId, $defaultInventoryId);
        $processedProducts = $processed['items'];
        $calculatedTotalAmount = $processed['total_amount'];
        $totalItems = $processed['total_items'];

        // Generar número de borrador legible y único (SIN tocar el consecutivo de la tienda)
        $prefix = 'DFT-' . ($store->invoice_prefix ? $store->invoice_prefix . '-' : '');
        $draftNumber = $this->generateUniqueDraftNumber($prefix, $store->id);

        $grandTotal = $request->filled('grand_total')
            ? (float) $request->grand_total
            : $calculatedTotalAmount + (float) ($request->tax ?? 0) - (float) ($request->discount ?? 0);

        $clientName = $request->client_name;
        if (empty($clientName) && $request->filled('client_id')) {
            $client = Client::where('id', $request->client_id)->where('organization_id', $orgId)->first();
            $clientName = $client?->name;
        }
        if (empty($clientName)) {
            $clientName = 'Consumidor Final';
        }

        $invoiceData = [
            'organization_id' => $orgId,
            'user_id' => $userId,
            'store_id' => $store->id,
            'client_id' => $request->client_id,
            'seller_id' => $request->seller_id ?? Auth::user()->seller_id,
            'client_name' => $clientName,
            'invoice_number' => $draftNumber,
            'invoice_date' => $request->invoice_date ?? now()->toDateString(),
            'invoice_note' => $request->invoice_note,
            'total' => $totalItems,
            'discount' => $request->discount ?? 0,
            'tax' => $request->tax ?? 0,
            'grand_total' => $grandTotal,
            'payment_method' => $request->payment_method ?? 'CASH',
            'payment_date' => $request->payment_date ?? now()->toDateString(),
            'payment_metadata' => $request->payment_metadata,
            'invoice_status' => 'draft',
            'invoice_type' => 'draft',
            'cash_session_id' => $request->cash_session_id, // Guardado como referencia, SIN transacciones en caja
            'source' => $request->source ?? 'POS',
        ];

        DB::beginTransaction();
        try {
            $draft = Invoice::create($invoiceData);

            foreach ($processedProducts as $index => $item) {
                $draft->invoiceDetails()->create([
                    'item_type' => $item['item_type'] ?? 'product',
                    'description' => $item['description'] ?? null,
                    'product_id' => $item['product_id'],
                    'inventory_id' => $item['inventory_id'],
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                    'cost' => $item['cost'],
                    'total' => $item['total'],
                    'discount' => $item['discount'],
                    'tax' => $item['tax'],
                    'grand_total' => $item['grand_total'],
                    'sort_order' => $index,
                ]);
            }

            DB::commit();

            return (new InvoiceResource($draft->load(['client', 'seller', 'invoiceDetails.product', 'store'])))
                ->response()
                ->setStatusCode(Response::HTTP_CREATED);
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);
            return response()->json(['message' => 'Error al guardar el borrador.', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Display a specific draft.
     */
    public function show(string $id)
    {
        $orgId = Auth::user()->organization_id;

        $draft = Invoice::where('id', $id)
            ->where('organization_id', $orgId)
            ->first();

        if (!$draft) {
            return response()->json(['message' => 'Borrador no encontrado.'], Response::HTTP_NOT_FOUND);
        }

        if ($draft->invoice_status !== 'draft') {
            return response()->json(['message' => 'El documento solicitado no es un borrador.'], Response::HTTP_BAD_REQUEST);
        }

        $this->authorize('view', $draft);

        // SEGURIDAD: Verificar tienda
        $allowedStoreIds = Auth::user()->allowedStoreIds();
        if ($allowedStoreIds !== null && !in_array($draft->store_id, $allowedStoreIds, true)) {
            return response()->json(['message' => 'No autorizado para esta tienda.'], Response::HTTP_FORBIDDEN);
        }

        return new InvoiceResource($draft->load(['client', 'seller', 'invoiceDetails.product', 'store']));
    }

    /**
     * Update an existing draft without inventory or cash session movements.
     */
    public function update(UpdateDraftInvoiceRequest $request, string $id)
    {
        $orgId = Auth::user()->organization_id;

        $allowDrafts = filter_var(
            Setting::where('organization_id', $orgId)->where('key', 'allow_invoice_drafts')->value('value'),
            FILTER_VALIDATE_BOOLEAN
        );
        if (!$allowDrafts) {
            return response()->json(['message' => 'La organización no tiene habilitada la gestión de borradores.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $draft = Invoice::where('id', $id)
            ->where('organization_id', $orgId)
            ->first();

        if (!$draft) {
            return response()->json(['message' => 'Borrador no encontrado.'], Response::HTTP_NOT_FOUND);
        }

        if ($draft->invoice_status !== 'draft') {
            return response()->json(['message' => 'Solo se pueden modificar facturas en estado borrador.'], Response::HTTP_BAD_REQUEST);
        }

        $this->authorize('update', $draft);

        // SEGURIDAD: Verificar tienda original
        $allowedStoreIds = Auth::user()->allowedStoreIds();
        if ($allowedStoreIds !== null && !in_array($draft->store_id, $allowedStoreIds, true)) {
            return response()->json(['message' => 'No autorizado para esta tienda.'], Response::HTTP_FORBIDDEN);
        }

        // Si se cambia de tienda, verificar la nueva tienda
        if ($request->filled('store_id') && $request->store_id !== $draft->store_id) {
            if ($allowedStoreIds !== null && !in_array($request->store_id, $allowedStoreIds, true)) {
                return response()->json(['message' => 'No autorizado para la nueva tienda.'], Response::HTTP_FORBIDDEN);
            }
            $draft->store_id = $request->store_id;
        }

        DB::beginTransaction();
        try {
            if ($request->has('client_id')) {
                $draft->client_id = $request->client_id;
            }
            if ($request->filled('client_name')) {
                $draft->client_name = $request->client_name;
            }
            if ($request->filled('invoice_date')) {
                $draft->invoice_date = $request->invoice_date;
            }
            if ($request->has('invoice_note')) {
                $draft->invoice_note = $request->invoice_note;
            }
            if ($request->filled('discount')) {
                $draft->discount = $request->discount;
            }
            if ($request->filled('tax')) {
                $draft->tax = $request->tax;
            }
            if ($request->filled('grand_total')) {
                $draft->grand_total = $request->grand_total;
            }
            if ($request->filled('payment_method')) {
                $draft->payment_method = $request->payment_method;
            }
            if ($request->filled('payment_date')) {
                $draft->payment_date = $request->payment_date;
            }
            if ($request->has('payment_metadata')) {
                $draft->payment_metadata = $request->payment_metadata;
            }
            if ($request->has('cash_session_id')) {
                $draft->cash_session_id = $request->cash_session_id;
            }
            if ($request->has('seller_id')) {
                $draft->seller_id = $request->seller_id;
            }

            // Si se suministran productos, reemplazar los detalles del borrador
            if ($request->has('products') && !empty($request->products)) {
                $productArray = is_array($request->products)
                    ? $request->products
                    : json_decode($request->products, true, 512, JSON_THROW_ON_ERROR);

                $hasFreeItems = false;
                foreach ($productArray as $p) {
                    if (($p['item_type'] ?? '') === 'free') {
                        $hasFreeItems = true;
                        break;
                    }
                }
                if ($hasFreeItems) {
                    $allowFree = filter_var(
                        Setting::where('organization_id', $orgId)->where('key', 'allow_free_invoice_items')->value('value'),
                        FILTER_VALIDATE_BOOLEAN
                    );
                    if (!$allowFree) {
                        DB::rollBack();
                        return response()->json(['message' => 'La organización no tiene habilitada la facturación de conceptos libres.'], Response::HTTP_UNPROCESSABLE_ENTITY);
                    }
                }

                $defaultInventoryId = $this->resolveDefaultInventoryId($orgId, $draft->store_id, $request->inventory_id);
                $processed = $this->processProductItems($productArray, $orgId, $defaultInventoryId);
                $processedProducts = $processed['items'];

                $draft->total = $processed['total_items'];

                if (!$request->filled('grand_total')) {
                    $draft->grand_total = $processed['total_amount'] + (float) $draft->tax - (float) $draft->discount;
                }

                // Eliminar detalles previos
                $draft->invoiceDetails()->delete();

                // Insertar nuevos detalles
                foreach ($processedProducts as $index => $item) {
                    $draft->invoiceDetails()->create([
                        'item_type' => $item['item_type'] ?? 'product',
                        'description' => $item['description'] ?? null,
                        'product_id' => $item['product_id'],
                        'inventory_id' => $item['inventory_id'],
                        'quantity' => $item['quantity'],
                        'price' => $item['price'],
                        'cost' => $item['cost'],
                        'total' => $item['total'],
                        'discount' => $item['discount'],
                        'tax' => $item['tax'],
                        'grand_total' => $item['grand_total'],
                        'sort_order' => $index,
                    ]);
                }
            }

            $draft->save();

            DB::commit();

            return new InvoiceResource($draft->fresh(['client', 'seller', 'invoiceDetails.product', 'store']));
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);
            return response()->json(['message' => 'Error al actualizar el borrador.', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Delete an un-converted draft and its items.
     */
    public function destroy(string $id): JsonResponse
    {
        $orgId = Auth::user()->organization_id;

        $draft = Invoice::where('id', $id)
            ->where('organization_id', $orgId)
            ->first();

        if (!$draft) {
            return response()->json(['message' => 'Borrador no encontrado.'], Response::HTTP_NOT_FOUND);
        }

        if ($draft->invoice_status !== 'draft') {
            return response()->json(['message' => 'Solo se pueden eliminar facturas en estado borrador.'], Response::HTTP_BAD_REQUEST);
        }

        $this->authorize('delete', $draft);

        // SEGURIDAD: Verificar tienda
        $allowedStoreIds = Auth::user()->allowedStoreIds();
        if ($allowedStoreIds !== null && !in_array($draft->store_id, $allowedStoreIds, true)) {
            return response()->json(['message' => 'No autorizado para esta tienda.'], Response::HTTP_FORBIDDEN);
        }

        DB::beginTransaction();
        try {
            $draft->invoiceDetails()->delete();
            $draft->delete();
            DB::commit();

            return response()->json(['message' => 'Borrador eliminado exitosamente.']);
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);
            return response()->json(['message' => 'Error al eliminar el borrador.', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Convert draft into an official invoice with transaction, stock deduction, and payment handling.
     */
    public function convert(Request $request, string $id)
    {
        $this->authorize('create', Invoice::class);

        $orgId = Auth::user()->organization_id;
        $userId = Auth::id();

        $allowDrafts = filter_var(
            Setting::where('organization_id', $orgId)->where('key', 'allow_invoice_drafts')->value('value'),
            FILTER_VALIDATE_BOOLEAN
        );
        if (!$allowDrafts) {
            return response()->json(['message' => 'La organización no tiene habilitada la gestión de borradores.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Chequeo de límite de facturas del plan
        $organization = \App\Models\Organization::find($orgId);
        if ($organization && !PlanLimits::for($organization)->canCreateInvoice()) {
            return response()->json([
                'message' => 'Has alcanzado el límite de facturas mensuales de tu plan. Actualiza tu plan para seguir facturando.',
                'error_code' => 'PLAN_LIMIT_INVOICES',
            ], Response::HTTP_FORBIDDEN);
        }

        $lockKey = 'convert_invoice_draft_' . $userId . '_' . $id;
        $lock = Cache::lock($lockKey, 5);

        if (!$lock->get()) {
            return response()->json(['message' => 'Este borrador ya está siendo procesado.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        DB::beginTransaction();
        try {
            $draft = Invoice::where('id', $id)
                ->where('organization_id', $orgId)
                ->lockForUpdate()
                ->first();

            if (!$draft) {
                DB::rollBack();
                $lock->release();
                return response()->json(['message' => 'Borrador no encontrado.'], Response::HTTP_NOT_FOUND);
            }

            if ($draft->invoice_status !== 'draft') {
                DB::rollBack();
                $lock->release();
                return response()->json(['message' => 'La factura ya ha sido convertida o no es un borrador.'], Response::HTTP_BAD_REQUEST);
            }

            // SEGURIDAD: Verificar tienda asignada
            $allowedStoreIds = Auth::user()->allowedStoreIds();
            if ($allowedStoreIds !== null && !in_array($draft->store_id, $allowedStoreIds, true)) {
                DB::rollBack();
                $lock->release();
                return response()->json(['message' => 'No autorizado para esta tienda.'], Response::HTTP_FORBIDDEN);
            }

            // Determinar método de pago y si es a crédito
            $paymentMethod = $request->payment_method ?? $draft->payment_method ?? 'CASH';
            $isCredit = filter_var($request->isCredit, FILTER_VALIDATE_BOOLEAN)
                || in_array(strtoupper($paymentMethod), ['CREDIT', 'CREDITO'], true);

            $clientId = $request->client_id ?? $draft->client_id;
            if ($isCredit && empty($clientId)) {
                DB::rollBack();
                $lock->release();
                return response()->json(['message' => 'Debe seleccionar un cliente registrado para crear una factura a crédito.'], Response::HTTP_BAD_REQUEST);
            }

            $paymentMetadata = $request->payment_metadata ?? $draft->payment_metadata;
            $grandTotal = (float) ($request->grand_total ?? $draft->grand_total);

            // Validar pagos múltiples si aplica
            if ($paymentMethod === 'MULTIPLE') {
                if (!is_array($paymentMetadata) || !isset($paymentMetadata['payments']) || !is_array($paymentMetadata['payments'])) {
                    DB::rollBack();
                    $lock->release();
                    return response()->json(['message' => 'Los metadatos de pago múltiple son requeridos y deben ser válidos.'], Response::HTTP_BAD_REQUEST);
                }

                $totalPayments = 0.0;
                foreach ($paymentMetadata['payments'] as $pay) {
                    if (!isset($pay['amount']) || !is_numeric($pay['amount']) || $pay['amount'] < 0) {
                        DB::rollBack();
                        $lock->release();
                        return response()->json(['message' => 'Cada método de pago debe especificar un monto numérico positivo.'], Response::HTTP_BAD_REQUEST);
                    }
                    $totalPayments += (float) $pay['amount'];
                }

                if ($totalPayments < $grandTotal - 0.05) {
                    DB::rollBack();
                    $lock->release();
                    return response()->json([
                        'message' => 'La suma de los métodos de pago (C$ ' . number_format($totalPayments, 2) . ') es menor al total de la factura (C$ ' . number_format($grandTotal, 2) . ').'
                    ], Response::HTTP_BAD_REQUEST);
                }
            }

            // Validar si tiene conceptos libres y si están permitidos
            if ($draft->invoiceDetails()->where('item_type', 'free')->exists()) {
                $allowFree = filter_var(
                    Setting::where('organization_id', $orgId)->where('key', 'allow_free_invoice_items')->value('value'),
                    FILTER_VALIDATE_BOOLEAN
                );
                if (!$allowFree) {
                    DB::rollBack();
                    $lock->release();
                    return response()->json(['message' => 'La organización no tiene habilitada la facturación de conceptos libres.'], Response::HTTP_UNPROCESSABLE_ENTITY);
                }
            }

            $movementService = app(InventoryMovementService::class);
            $allowNegativeStock = $movementService->allowsNegativeStock($draft->store_id, $orgId);

            // 1. Validar existencias de todos los productos del borrador (omitir conceptos libres)
            $details = $draft->invoiceDetails()->with('product')->get();
            if ($details->isEmpty()) {
                DB::rollBack();
                $lock->release();
                return response()->json(['message' => 'El borrador no tiene productos.'], Response::HTTP_BAD_REQUEST);
            }

            foreach ($details as $item) {
                if ($item->item_type === 'free' || empty($item->product_id)) {
                    continue;
                }
                $qty = (float) $item->quantity;
                $invDetail = InventoryDetail::where('product_id', $item->product_id)
                    ->where('inventory_id', $item->inventory_id)
                    ->lockForUpdate()
                    ->first();

                if (!$invDetail) {
                    DB::rollBack();
                    $lock->release();
                    return response()->json(['message' => "Producto con ID {$item->product_id} no encontrado en el inventario."], Response::HTTP_NOT_FOUND);
                }

                if ($qty > 0 && $invDetail->quantity < $qty && !$allowNegativeStock) {
                    DB::rollBack();
                    $lock->release();
                    return response()->json([
                        'message' => 'Producto sin stock suficiente.',
                        'product_name' => $invDetail->product->name ?? 'N/A',
                        'quantity_available' => $invDetail->quantity,
                        'quantity_requested' => $qty,
                    ], Response::HTTP_BAD_REQUEST);
                }
            }

            // 2. Asignar el consecutivo oficial de la tienda
            $store = Store::where('id', $draft->store_id)->lockForUpdate()->firstOrFail();
            $nextInvoiceNumber = $store->invoice_number + 1;
            do {
                $invoiceNumber = $store->invoice_prefix
                    ? $store->invoice_prefix . '-' . str_pad($nextInvoiceNumber, 6, '0', STR_PAD_LEFT)
                    : str_pad($nextInvoiceNumber, 6, '0', STR_PAD_LEFT);

                $exists = Invoice::where('store_id', $draft->store_id)
                    ->where('invoice_number', $invoiceNumber)
                    ->exists();

                if ($exists) {
                    $nextInvoiceNumber++;
                }
            } while ($exists);

            $store->invoice_number = $nextInvoiceNumber;
            $store->save();

            // 3. Procesar datos de moneda y pagos en efectivo
            $paidInNio = 0.0;
            $paidInUsd = 0.0;
            $exchangeRateVal = 0.0;

            if ($paymentMethod === 'CASH' && is_array($paymentMetadata)) {
                $paidInNio = (float) ($paymentMetadata['paid_in_nio'] ?? $paymentMetadata['paid_nio'] ?? 0);
                $paidInUsd = (float) ($paymentMetadata['paid_in_usd'] ?? $paymentMetadata['paid_usd'] ?? 0);
                $exchangeRateVal = (float) ($paymentMetadata['exchange_rate'] ?? 0);
            } elseif ($paymentMethod === 'MULTIPLE' && is_array($paymentMetadata) && isset($paymentMetadata['payments'])) {
                foreach ($paymentMetadata['payments'] as $p) {
                    if (($p['method'] ?? '') === 'CASH') {
                        $paidInNio += (float) ($p['paid_in_nio'] ?? $p['paid_nio'] ?? 0);
                        $paidInUsd += (float) ($p['paid_in_usd'] ?? $p['paid_usd'] ?? 0);
                        $exchangeRateVal = (float) ($p['exchange_rate'] ?? $exchangeRateVal);
                    }
                }
            }

            // 4. Actualizar el modelo Invoice a factura formal
            $draft->invoice_number = $invoiceNumber;
            $draft->invoice_status = $isCredit ? 'credit' : 'completed';
            $draft->invoice_type = $isCredit ? 'credit' : 'cash';
            $draft->payment_method = $paymentMethod;
            $draft->payment_metadata = $paymentMetadata;
            $draft->payment_date = $request->payment_date ?? $draft->payment_date ?? now()->toDateString();
            $draft->cash_session_id = $request->cash_session_id ?? $draft->cash_session_id;
            $draft->client_id = $clientId;
            if ($request->filled('client_name')) {
                $draft->client_name = $request->client_name;
            }
            if ($request->has('invoice_note')) {
                $draft->invoice_note = $request->invoice_note;
            }
            if ($request->filled('seller_id')) {
                $draft->seller_id = $request->seller_id;
            }

            $draft->paid_in_nio = $paidInNio;
            $draft->paid_in_usd = $paidInUsd;
            $draft->exchange_rate = $exchangeRateVal;
            $draft->converted_at = now();
            $draft->save();

            // 5. Aplicar efectos de inventario (descontar stock y registrar movimientos, omitir conceptos libres)
            foreach ($details as $item) {
                if ($item->item_type === 'free' || empty($item->product_id)) {
                    continue;
                }
                $quantity = (float) $item->quantity;
                $invDetail = InventoryDetail::where('product_id', $item->product_id)
                    ->where('inventory_id', $item->inventory_id)
                    ->lockForUpdate()
                    ->first();

                if ($invDetail) {
                    $movementService->recordMovement([
                        'inventory_detail_id' => $invDetail->id,
                        'type' => $quantity >= 0 ? 'sale' : 'return',
                        'quantity' => abs($quantity),
                        'reason' => "Venta en Factura N° {$draft->invoice_number}",
                        'user_id' => $userId,
                        'seller_id' => $draft->seller_id,
                        'reference_id' => $draft->id,
                        'reference_type' => Invoice::class,
                    ]);
                }
            }

            // 6. Si es venta a crédito, generar registro de crédito y abono inicial
            if ($isCredit && $draft->client_id) {
                $credit = $draft->credit()->create([
                    'user_id' => $userId,
                    'organization_id' => $orgId,
                    'store_id' => $draft->store_id,
                    'client_id' => $draft->client_id,
                    'invoice_id' => $draft->id,
                    'total' => $draft->grand_total,
                    'debt' => $draft->grand_total,
                    'credit_status' => 'active',
                ]);

                if ($request->init_payment) {
                    $initPayment = (float) $request->init_payment;
                    if ($initPayment > $draft->grand_total) {
                        DB::rollBack();
                        $lock->release();
                        return response()->json(['message' => 'El abono inicial no puede ser mayor al total.'], Response::HTTP_BAD_REQUEST);
                    }

                    $credit->creditDetails()->create([
                        'amount' => $initPayment,
                        'date' => $request->payment_date ?? now()->toDateString(),
                        'note' => 'Pago inicial',
                        'payment_method' => in_array(strtoupper($paymentMethod), ['CREDIT', 'CREDITO'], true) ? 'CASH' : $paymentMethod,
                        'payment_metadata' => $request->payment_metadata,
                        'cash_session_id' => $request->cash_session_id ?? $draft->cash_session_id,
                    ]);

                    $credit->debt -= $initPayment;
                    $credit->save();
                }
            }

            DB::commit();
            $lock->release();

            // Disparar eventos de creación
            event(new \App\Events\InvoiceCreated($draft));
            if ($draft->credit) {
                event(new \App\Events\CreditCreated($draft->credit));
            }

            return response()->json([
                'message' => 'Borrador facturado exitosamente.',
                'invoice' => new InvoiceResource($draft->fresh(['client', 'seller', 'invoiceDetails.product', 'store', 'credit']))
            ], Response::HTTP_OK);
        } catch (\Throwable $e) {
            DB::rollBack();
            $lock->release();
            report($e);
            return response()->json(['message' => 'Error al facturar el borrador.', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Resolve default inventory ID for store.
     */
    protected function resolveDefaultInventoryId(string $orgId, string $storeId, ?string $requestedInventoryId = null): ?string
    {
        if ($requestedInventoryId) {
            return $requestedInventoryId;
        }

        $invStore = DB::table('inventory_store')->where('store_id', $storeId)->first();
        if ($invStore) {
            return $invStore->inventory_id;
        }

        $storeInv = DB::table('inventories')
            ->where('organization_id', $orgId)
            ->where('store_id', $storeId)
            ->first();

        if ($storeInv) {
            return $storeInv->id;
        }

        $firstInv = DB::table('inventories')->where('organization_id', $orgId)->first();
        return $firstInv?->id;
    }

    /**
     * Parse and structure product items for draft insertion.
     */
    protected function processProductItems(array $productArray, string $orgId, ?string $defaultInventoryId): array
    {
        $items = [];
        $totalAmount = 0.0;
        $totalItems = 0;

        foreach ($productArray as $product) {
            $itemType = $product['item_type'] ?? 'product';
            $qty = (float) ($product['quantity'] ?? 1);
            $price = (float) ($product['price'] ?? 0);

            if ($itemType === 'free') {
                $cost = isset($product['cost']) && is_numeric($product['cost']) ? (float)$product['cost'] : 0.0;
                $description = trim($product['description'] ?? $product['name'] ?? '');
                $itemTotal = (float) ($product['total'] ?? ($price * $qty));
                $itemDiscount = (float) ($product['discount'] ?? 0);
                $itemTax = (float) ($product['tax'] ?? 0);
                $itemGrandTotal = (float) ($product['grand_total'] ?? ($itemTotal - $itemDiscount + $itemTax));

                $totalAmount += $itemGrandTotal;
                $totalItems += abs($qty);

                $items[] = [
                    'item_type' => 'free',
                    'description' => $description,
                    'product_id' => null,
                    'inventory_id' => null,
                    'quantity' => $qty,
                    'price' => $price,
                    'cost' => $cost,
                    'total' => $itemTotal,
                    'discount' => $itemDiscount,
                    'tax' => $itemTax,
                    'grand_total' => $itemGrandTotal,
                ];
                continue;
            }

            $prodId = $product['product_id'] ?? null;
            $invId = $product['inventory_id'] ?? $defaultInventoryId;

            // Compatibilidad SKU
            if (empty($prodId) && !empty($product['sku'])) {
                $productObj = Product::where('organization_id', $orgId)->where('sku', $product['sku'])->first();
                if (!$productObj) {
                    $productObj = Product::create([
                        'organization_id' => $orgId,
                        'sku' => $product['sku'],
                        'name' => $product['name'] ?? ('Producto ' . $product['sku']),
                        'price' => $price,
                        'cost' => 0,
                        'status' => 'active',
                    ]);
                }
                $prodId = $productObj->id;
            }

            $cost = Product::where('id', $prodId)->value('cost') ?? 0;
            $itemTotal = (float) ($product['total'] ?? ($price * $qty));
            $itemDiscount = (float) ($product['discount'] ?? 0);
            $itemTax = (float) ($product['tax'] ?? 0);
            $itemGrandTotal = (float) ($product['grand_total'] ?? ($itemTotal - $itemDiscount + $itemTax));

            $totalAmount += $itemGrandTotal;
            $totalItems += abs($qty);

            $items[] = [
                'item_type' => 'product',
                'description' => null,
                'product_id' => $prodId,
                'inventory_id' => $invId,
                'quantity' => $qty,
                'price' => $price,
                'cost' => $cost,
                'total' => $itemTotal,
                'discount' => $itemDiscount,
                'tax' => $itemTax,
                'grand_total' => $itemGrandTotal,
            ];
        }

        return [
            'items' => $items,
            'total_amount' => $totalAmount,
            'total_items' => $totalItems,
        ];
    }

    /**
     * Generate unique human-readable draft invoice number.
     */
    protected function generateUniqueDraftNumber(string $prefix, string $storeId): string
    {
        do {
            $candidate = $prefix . date('ymd') . '-' . strtoupper(Str::random(5));
            $exists = Invoice::where('store_id', $storeId)
                ->where('invoice_number', $candidate)
                ->exists();
        } while ($exists);

        return $candidate;
    }
}
