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

use App\Http\Requests\StoreInvoiceRequest;
use Illuminate\Support\Facades\Auth;

use Maatwebsite\Excel\Facades\Excel;
use App\Exports\InvoiceExport;

class InvoiceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $orgId = Auth::user()->organization_id;
        $per_page = $request->query('per_page', 20);
        $order = $request->query('order', 'asc');
        $Invoice = Invoice::where('organization_id', $orgId)->orderBy('created_at', $order)->paginate($per_page);
        $store_id = $request->query('store_id');
        $date_from = $request->query('date_from') ? $request->query('date_from') . ' 00:00:00' : null;
        $date_to = $request->query('date_to') ? $request->query('date_to') . ' 23:59:59' : null;
        $number_invoice_from = $request->query('number_invoice_from');
        $number_invoice_to = $request->query('number_invoice_to');

        

        $method = $request->query('method');

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
        
        // Validar si falta el store_id cuando se filtra por número de factura
        if (($number_invoice_from || $number_invoice_to) && !$store_id) {
            return response()->json(['message' => 'Store ID is required when searching by invoice number.'], 400);
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

        $orgId = Auth::user()->organization_id;
        $userID = Auth::user()->id;
        $store =  Store::where('id', $request->store_id)->first();


        $clientID = $request->client_id ? $request->client_id : null;
     
        
        $productArray = is_array($request->products) ? $request->products : json_decode($request->products, true);
        $totalItems = 0;
        foreach($productArray as $product){
            $totalItems += $product['quantity'];
            $inventoryID = $product['inventory_id'];
            $productID = $product['product_id'];
            $productObjs = InventoryDetail::where('product_id', $productID)->where('inventory_id', $inventoryID)->first();
       
            if($productObjs->quantity < $product['quantity']){
               //return a error message which product is out of stock and return quantity available in stock and product name
                return response()->json(
                    [
                        'message' => 'Product is out of stock.',
                        'product_name' => $productObjs->product->name,
                        'quantity_available' => $productObjs->quantity,
                        'quantity_requested' => $product['quantity']

                ], 400);

            }   



        }   


        $invoiceNumber =  $store->invoice_prefix .'-'. str_pad($store->invoice_number + 1, 6, '0', STR_PAD_LEFT);



        $invoiceData = $request->only([
            'client_id',
            'store_id',
            'invoice_number',
            'invoice_date',
            'invoice_note',
            'client_name',
            'discount',
            'tax',
            'grand_total',
            'payment_method',
            'payment_date'
        ]);
        $invoiceData['invoice_number'] = $invoiceNumber;
        $invoiceData['total'] = $totalItems;


 

        $invoice = Invoice::create(
            array_merge(
                $invoiceData,
                [
                    'invoice_status' => $request->isCredit ? 'credit' : 'completed',
                    'user_id' => $userID,
                    'organization_id' => $orgId,
                
                ]
            )
        );

        //validate if the invoice is created
        if(!$invoice){
            return response()->json(['message' => 'Invoice could not be created.'], 400);
        }

        $store->invoice_number = $store->invoice_number + 1;
        $store->save();

        foreach($productArray as $product){
            $invoice->invoiceDetails()->create([
                'product_id' => $product['product_id'],
                'inventory_id' => $product['inventory_id'],
                'quantity' => $product['quantity'],
                'price' => $product['price'],
                'total' => $product['total']
            ]);

            $productObjs = InventoryDetail::where('product_id', $product['product_id'])->where('inventory_id', $product['inventory_id'])->first();
            $productObjs->quantity = $productObjs->quantity - $product['quantity'];

            $productObjs->save();

        }

        if($request->isCredit && $request->client_id){
            
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

            if($request->init_payment){
                if($request->init_payment > $request->grand_total){
                    return response()->json(['message' => 'Credit amount cannot be greater than grand total.'], 400);
                }

                $credit->creditDetails()->create([
                    'credit_id' => $credit->id,
                    'amount' => $request->init_payment,
                    'date' => $request->payment_date,
                    'note' => 'Initial payment'
                ]);

                $credit->debt = $credit->debt - $request->init_payment;
                $credit->save();
                
            }
        }

        //return response()->json(['message' => 'Invoice created successfully.', 'data' =>], 201);
        return new InvoiceResource($invoice);
    }

    /**
     * Display the specified resource.
     */
    public function show(Invoice $invoice)
    {
        return new InvoiceResource($invoice);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function cancel(Invoice $invoice)
    {
        $invoice->invoice_status = 'canceled';
        $invoice->save();

        $invoiceDetails = $invoice->invoiceDetails;

        foreach($invoiceDetails as $invoiceDetail){
            $productObjs = InventoryDetail::where('product_id', $invoiceDetail->product_id)->where('inventory_id', $invoiceDetail->inventory_id)->first();
            $productObjs->quantity = $productObjs->quantity + $invoiceDetail->quantity;
            $productObjs->save();
        }

        return response()->json(['message' => 'Invoice cancelled successfully.']);
    }

    
    public function exportInvoices(Request $request)
    {

        $orgId = Auth::user()->organization_id;
        $invoices = Invoice::where('organization_id', $orgId)
            ->where('store_id', $request->store_id)
            ->get();

            

        
       return Excel::download(new InvoiceExport($invoices), 'reporte_invoices.xlsx');
    }
}
