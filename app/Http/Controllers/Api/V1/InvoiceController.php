<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\InventoryDetail;
use App\Models\CreditDetail;
use App\Models\Invoice;
use App\Models\Store;
use Illuminate\Http\Request;
use App\Http\Resources\InvoiceCollection;
use App\Http\Resources\InvoiceResource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\JsonResponse;
use App\Http\Requests\StoreInvoiceRequest;

use Maatwebsite\Excel\Facades\Excel;
use App\Exports\InvoiceExport;

class InvoiceController extends Controller
{
    use \Illuminate\Foundation\Auth\Access\AuthorizesRequests;

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Invoice::class);
        $orgId = Auth::user()->organization_id;
        $per_page = $request->query('per_page', 20);
        $order = $request->query('order', 'asc');
        $Invoice = Invoice::where('organization_id', $orgId)->orderBy('created_at', $order)->paginate($per_page);
        $store_id = $request->query('store_id');
        $date_from = $request->query('date_from') ? $request->query('date_from') . ' 00:00:00' : null;
        $date_to = $request->query('date_to') ? $request->query('date_to') . ' 23:59:59' : null;
        $number_invoice_from = $request->query('number_invoice_from');
        $number_invoice_to = $request->query('number_invoice_to');
        $invoice_status = $request->query('invoice_status');
        $client_name = $request->query('client_name');
        $method = $request->query('method');
        $seller_id = $request->query('seller_id');

        $search = $request->query('search');

        $query = Invoice::where('organization_id', $orgId);

        // Agregar filtro por tienda
        if ($store_id) {
            $query->where('store_id', $store_id);
        }
        
        // Agregar filtro por método de pago
        if ($method) {
            $query->where('payment_method', $method);
        }
        
        // Agregar filtro por rango de fechas
        if ($date_from && $date_to) {
            $query->whereBetween('created_at', [$date_from, $date_to]);
        } elseif ($date_from) {
            $query->where('created_at', '>=', $date_from);
        } elseif ($date_to) {
            $query->where('created_at', '<=', $date_to);
        }
        
        // Agregar filtro por búsqueda
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', '%' . $search . '%')
                  ->orWhere('client_name', 'like', '%' . $search . '%');
            });
        }
        
        // Agregar filtro por rango de números de factura
        if ($number_invoice_from && $number_invoice_to && $store_id) {
            $prefix = Store::where('id', $store_id)->first()->invoice_prefix;
            $number_invoice_from = $prefix . '-' . str_pad($number_invoice_from, 6, '0', STR_PAD_LEFT);
            $number_invoice_to = $prefix . '-' . str_pad($number_invoice_to, 6, '0', STR_PAD_LEFT);
            $query->whereBetween('invoice_number', [$number_invoice_from, $number_invoice_to]);
        } elseif ($number_invoice_from && $store_id) {
            $prefix = Store::where('id', $store_id)->first()->invoice_prefix;
            $number_invoice_from = $prefix . '-' . str_pad($number_invoice_from, 6, '0', STR_PAD_LEFT);
            $query->where('invoice_number', '>=', $number_invoice_from);
        } elseif ($number_invoice_to && $store_id) {
            $prefix = Store::where('id', $store_id)->first()->invoice_prefix;
            $number_invoice_to = $prefix . '-' . str_pad($number_invoice_to, 6, '0', STR_PAD_LEFT);
            $query->where('invoice_number', '<=', $number_invoice_to);
        }

        if($client_name) {
            $query->where('client_name', 'like', '%' . $client_name . '%');
        }
        
        // Validar si falta el store_id cuando se filtra por número de factura
        if (($number_invoice_from || $number_invoice_to) && !$store_id) {
            return response()->json(['message' => 'Store ID is required when searching by invoice number.'], 400);
        }

        if($invoice_status) {
            $query->where('invoice_status', $invoice_status);
        }

     
        if($seller_id) {
          
            $query->where('seller_id', $seller_id);
        }


    
        // Agregar opción de ordenamiento
        $sort_by = $request->query('sort_by', 'created_at'); // Valor por defecto: created_at
        $order = $request->query('order', 'asc'); // Valor por defecto: asc
        $allowed_sort_fields = ['grand_total', 'created_at', 'client_name', 'invoice_number'];

        if (in_array($sort_by, $allowed_sort_fields)) {
            $query->orderBy($sort_by, $order);
        } else {
            return response()->json(['message' => 'Invalid sort_by field.'], 400);
        }

        // Ordenar y paginar los resultados
        $Invoice = $query->paginate($per_page);

        return new InvoiceCollection($Invoice);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreInvoiceRequest $request)
    {
        $this->authorize('create', Invoice::class);

        $lockKey = 'create_invoice_' . Auth::id() . '_' . md5(json_encode($request->all()));
        $lock = Cache::lock($lockKey, 5); // Bloqueo de 5 segundos para evitar doble envío

        if (!$lock->get()) {
            return response()->json(['message' => 'Esta factura ya está siendo procesada o ya fue enviada.'], 422);
        }

        DB::beginTransaction();
        try {
            $result = $this->storeInternal($request);
            if ($result instanceof \Illuminate\Http\JsonResponse) {
                DB::rollBack();
                $lock->release();
                return $result;
            }
            DB::commit();

            // Disparar eventos de notificación después del commit exitoso
            $invoice = $result->resource;
            event(new \App\Events\InvoiceCreated($invoice));

            if ($invoice->credit) {
                event(new \App\Events\CreditCreated($invoice->credit));
            }

            return $result;
        } catch (\Throwable $e) {
            DB::rollBack();
            $lock->release();
            report($e);
            return response()->json(['message' => 'Error al procesar la factura.', 'error' => $e->getMessage()], 500);
        }
    }

    protected function storeInternal(StoreInvoiceRequest $request)
    {
        if ($request->isCredit && empty($request->client_id)) {
            return response()->json(['message' => 'Debe seleccionar un cliente registrado para crear una factura a crédito.'], 400);
        }

        if ($request->payment_method === 'MULTIPLE') {
            $metadata = $request->payment_metadata;
            if (!is_array($metadata) || !isset($metadata['payments']) || !is_array($metadata['payments'])) {
                return response()->json(['message' => 'Los metadatos de pago múltiple son requeridos y deben ser válidos.'], 400);
            }

            $totalPayments = 0.0;
            foreach ($metadata['payments'] as $pay) {
                if (!isset($pay['amount']) || !is_numeric($pay['amount']) || $pay['amount'] < 0) {
                    return response()->json(['message' => 'Cada método de pago debe especificar un monto numérico positivo.'], 400);
                }
                $totalPayments += (float) $pay['amount'];

                if (($pay['method'] ?? '') === 'TRANSFER') {
                    if (empty($pay['bank']) || empty($pay['reference'])) {
                        return response()->json(['message' => 'Los pagos por transferencia deben incluir el banco de origen y la referencia.'], 400);
                    }
                }

                if (($pay['method'] ?? '') === 'CARD') {
                    if (empty($pay['card_last_four']) || empty($pay['reference'])) {
                        return response()->json(['message' => 'Los pagos con tarjeta deben incluir los últimos 4 dígitos y la referencia.'], 400);
                    }
                }
            }

            $grandTotal = (float) $request->grand_total;
            if ($totalPayments < $grandTotal - 0.05) {
                return response()->json(['message' => 'La suma de los métodos de pago (C$ ' . number_format($totalPayments, 2) . ') es menor al total de la factura (C$ ' . number_format($grandTotal, 2) . ').'], 400);
            }
        }

        $orgId = Auth::user()->organization_id;
            $userID = Auth::id();
            $store = Store::where('id', $request->store_id)->lockForUpdate()->firstOrFail();
            $allowNegativeStock = true;
           
    
            $productArray = is_array($request->products)
                ? $request->products
                : json_decode($request->products, true, 512, JSON_THROW_ON_ERROR);
    
            $totalItems = 0;
    
            foreach ($productArray as $product) {
                $quantity = $product['quantity'];
                $totalItems += abs($quantity); // opcional, si querés contar total de unidades sin importar si son devoluciones
            
                $productObj = InventoryDetail::with('product')
                    ->where('product_id', $product['product_id'])
                    ->where('inventory_id', $product['inventory_id'])
                    ->first();
            
                if (!$productObj) {
                    return response()->json(['message' => 'Producto no encontrado en el inventario.'], 404);
                }
            
                if ($quantity > 0 && $productObj->quantity < $quantity && !$allowNegativeStock) {
                    return response()->json([
                        'message' => 'Producto sin stock suficiente.',
                        'product_name' => $productObj->product->name ?? 'N/A',
                        'quantity_available' => $productObj->quantity,
                        'quantity_requested' => $quantity
                    ], 400);
                }
            }
    
            $nextInvoiceNumber = $store->invoice_number + 1;
            do {
                $invoiceNumber = $store->invoice_prefix
                    ? $store->invoice_prefix . '-' . str_pad($nextInvoiceNumber, 6, '0', STR_PAD_LEFT)
                    : str_pad($nextInvoiceNumber, 6, '0', STR_PAD_LEFT);

                $exists = Invoice::where('store_id', $request->store_id)
                    ->where('invoice_number', $invoiceNumber)
                    ->exists();

                if ($exists) {
                    $nextInvoiceNumber++;
                }
            } while ($exists);
    
            $invoiceData = $request->only([
                'client_id',
                'seller_id',
                'store_id',
                'invoice_date',
                'invoice_note',
                'client_name',
                'discount',
                'tax',
                'grand_total',
                'payment_method',
                'payment_date',
                'payment_metadata'
            ]);
            $invoiceData['seller_id'] = $request->seller_id ?? Auth::user()->seller_id;
            $invoiceData['invoice_number'] = $invoiceNumber;
            $invoiceData['total'] = $totalItems;
            $invoiceData['invoice_status'] = $request->isCredit ? 'credit' : 'completed';
            $invoiceData['invoice_type'] = $request->isCredit ? 'credit' : 'cash';
            $invoiceData['user_id'] = $userID;
            $invoiceData['organization_id'] = $orgId;
    
            $invoice = Invoice::create($invoiceData);
    
            if (!$invoice) {
                return response()->json(['message' => 'No se pudo crear la factura.'], 400);
            }
    
            // Actualizar número de factura al consecutivo utilizado
            $store->invoice_number = $nextInvoiceNumber;
            $store->save();
    
            // Detalles y actualización de inventario
            foreach ($productArray as $index => $product) {
                $invoice->invoiceDetails()->create([
                    'product_id' => $product['product_id'],
                    'inventory_id' => $product['inventory_id'],
                    'quantity' => $product['quantity'],
                    'price' => $product['price'],
                    'total' => $product['total'],
                    'discount' => $product['discount'] ?? 0,
                    'tax' => $product['tax'] ?? 0,
                    'grand_total' => $product['grand_total'] ?? $product['total'],
                    'sort_order' => $index
                ]);
                
                $quantity = (float) $product['quantity'];
                $operator = $quantity >= 0 ? '-' : '+';
                
                InventoryDetail::where('product_id', $product['product_id'])
                    ->where('inventory_id', $product['inventory_id'])
                    ->update([
                        'quantity' => DB::raw("quantity $operator " . abs($quantity))
                    ]);
            }
    
            // Si es crédito
            if ($request->isCredit && $request->client_id) {
                $credit = $invoice->credit()->create([
                    'user_id' => $userID,
                    'organization_id' => $orgId,
                    'store_id' => $request->store_id,
                    'client_id' => $request->client_id,
                    'invoice_id' => $invoice->id,
                    'total' => $request->grand_total,
                    'debt' => $request->grand_total,
                    'credit_status' => 'active'
                ]);
    
                if ($request->init_payment) {
                    if ($request->init_payment > $request->grand_total) {
                        return response()->json(['message' => 'El abono inicial no puede ser mayor al total.'], 400);
                    }
    
                    $credit->creditDetails()->create([
                        'amount' => $request->init_payment,
                        'date' => $request->payment_date,
                        'note' => 'Pago inicial'
                    ]);
    
                    $credit->debt -= $request->init_payment;
                    $credit->save();
                }
            }
    
            return new InvoiceResource($invoice);
    }

    /**
     * Display the specified resource.
     */
    public function show(Invoice $invoice)
    {
        $this->authorize('view', $invoice);

        return new InvoiceResource($invoice);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function cancel(Invoice $invoice)
    {
        $this->authorize('delete', $invoice);

        DB::beginTransaction();
        try {
            $this->cancelInternal($invoice);
            DB::commit();
            return response()->json(['message' => 'Invoice cancelled successfully.']);
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);
            return response()->json(['message' => 'Error canceling invoice.', 'error' => $e->getMessage()], 500);
        }
    }

    protected function cancelInternal(Invoice $invoice)
    {
        $invoice->invoice_status = 'canceled';
        $invoice->save();

            // Si la factura tiene un crédito asociado, lo anulamos también
            if ($invoice->credit) {
                $invoice->credit->credit_status = 'canceled';
                $invoice->credit->debt = 0; // Se elimina la deuda
                $invoice->credit->save();
            }

            $invoiceDetails = $invoice->invoiceDetails;

            foreach($invoiceDetails as $invoiceDetail){
                $productObjs = InventoryDetail::where('product_id', $invoiceDetail->product_id)
                    ->where('inventory_id', $invoiceDetail->inventory_id)
                    ->first();
                
                if ($productObjs) {
                    $productObjs->quantity = $productObjs->quantity + $invoiceDetail->quantity;
                    $productObjs->save();
                }
            }
    }

    public function replace(StoreInvoiceRequest $request, Invoice $invoice)
    {
        $this->authorize('create', Invoice::class);

        if ($invoice->invoice_status === 'canceled') {
            return response()->json(['message' => 'La factura original ya está anulada.'], 400);
        }

        $lockKey = 'replace_invoice_' . Auth::id() . '_' . $invoice->id . '_' . md5(json_encode($request->all()));
        $lock = Cache::lock($lockKey, 5); // Bloqueo de 5 segundos

        if (!$lock->get()) {
            return response()->json(['message' => 'Esta factura ya está siendo reemplazada.'], 422);
        }

        DB::beginTransaction();

        try {
            // 1. Anular la factura vieja
            $this->cancelInternal($invoice);

            // 2. Crear la nueva factura
            $newInvoiceResponse = $this->storeInternal($request);
            
            if ($newInvoiceResponse instanceof \Illuminate\Http\JsonResponse) {
                DB::rollBack();
                $lock->release();
                return $newInvoiceResponse; // Propagar el error
            }

            $newInvoice = $newInvoiceResponse->resource;

            // 3. Establecer trazabilidad bidireccional
            $invoice->replaced_by_invoice_id = $newInvoice->id;
            $invoice->save();

            $newInvoice->replaces_invoice_id = $invoice->id;
            $newInvoice->save();

            DB::commit();
            $lock->release();

            return response()->json([
                'message' => 'Factura reemplazada exitosamente.',
                'invoice' => new InvoiceResource($newInvoice)
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();
            $lock->release();
            report($e);
            return response()->json(['message' => 'Error al reemplazar la factura.', 'error' => $e->getMessage()], 500);
        }
    }

    
    public function exportInvoices(Request $request)
    {
        $this->authorize('viewAny', Invoice::class);

        $orgId = Auth::user()->organization_id;
        $invoices = Invoice::where('organization_id', $orgId)
            ->where('store_id', $request->store_id)
            ->get();

            

        
       return Excel::download(new InvoiceExport($invoices), 'reporte_invoices.xlsx');
    }
}
