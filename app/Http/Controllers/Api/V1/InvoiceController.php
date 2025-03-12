<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\InventoryDetail;
use App\Models\CreditDetail;
use App\Models\Invoice;
use Illuminate\Http\Request;
use App\Http\Resources\InvoiceCollection;
use App\Http\Resources\InvoiceResource;

use App\Http\Requests\StoreInvoiceRequest;
use Illuminate\Support\Facades\Auth;

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

        $search = $request->query('search');

        //filtrar por tienda opcional
        

        if($search){
            $Invoice = Invoice::where('organization_id', $orgId)
                ->where('invoice_number', 'like', '%'.$search.'%')
                ->orWhere('client_name', 'like', '%'.$search.'%')
                ->orderBy('created_at', $order)
                ->paginate($per_page);
        }
        
       

        return new InvoiceCollection($Invoice);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreInvoiceRequest $request)
    {

        $orgId = Auth::user()->organization_id;
        $userID = Auth::user()->id;
     
        $productArray = is_array($request->products) ? $request->products : json_decode($request->products, true);

        foreach($productArray as $product){
     
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


        $invoiceData = $request->only([
            'client_id',
            'store_id',
            'invoice_number',
            'invoice_date',
            'invoice_note',
            'client_name',
            'total',
            'discount',
            'tax',
            'grand_total',
            'payment_method',
            'payment_date'
        ]);


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
}
