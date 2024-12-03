<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\InventoryDetail;
use App\Models\CreditDetail;
use App\Models\Invoice;
use Illuminate\Http\Request;

use App\Http\Requests\StoreInvoiceRequest;
use Illuminate\Support\Facades\Auth;

class InvoiceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Invoice::with('invoiceDetails')->get();
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
            var_dump($product);
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
                'current' => 0,
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

                $credit->current = $request->init_payment;
                $credit->save();
                
            }
        }

        return response()->json(['message' => 'Invoice created successfully.'], 201);
        



    
        

    }

    /**
     * Display the specified resource.
     */
    public function show(Invoice $invoice)
    {
        return $invoice->load('invoiceDetails');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Invoice $invoice)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function cancel(Invoice $invoice)
    {
        //
    }
}
