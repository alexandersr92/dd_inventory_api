<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Credit;
use App\Models\CreditDetail;
use Illuminate\Http\Request;
use App\Http\Resources\CreditCollection;
use App\Http\Resources\CreditResource;
use Illuminate\Support\Facades\Auth;
class CreditController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request) 
    {
        $orgId = Auth::user()->organization_id;
        $per_page = $request->query('per_page', 20);
        $credits = Credit::where('organization_id', $orgId)->paginate($per_page);
        return new CreditCollection($credits);
    }


    /**
     * Display the specified resource.
     */
    public function show(Credit $credit)
    {
        return new CreditResource($credit);
    }

    /**
     * payment the specified resource in storage.
     */
    public function payment(Request $request, Credit $credit)
    {
        $orgId = Auth::user()->organization_id;
        $userID = Auth::user()->id;

        if($credit->organization_id !== $orgId){
            return response()->json(['message' => 'You are not authorized to make payment for this credit.'], 403);
        }

        if($credit->credit_status === 'paid'){
            return response()->json(['message' => 'This credit has already been paid.'], 400);
        }

        if($credit->current < $request->amount){
            return response()->json(['message' => 'The amount you are trying to pay is greater than the current credit amount.'], 400);
        }

        
        $credit->current = $credit->current - $request->amount;

        if( $credit->current === 0.00){
          
            $credit->credit_status = 'paid';
        }
        
      
        $credit->save();

        $creditDetail = new CreditDetail();
        $creditDetail->credit_id = $credit->id;
        $creditDetail->amount = $request->amount;
        $creditDetail->date = date('Y-m-d');
        $creditDetail->note = $request->note;
        $creditDetail->save();

        return response()->json(['message' => 'Payment has been made successfully.'], 200);
    }

}
