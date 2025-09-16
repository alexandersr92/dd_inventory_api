<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Credit;
use App\Models\CreditDetail;
use Illuminate\Http\Request;
use App\Http\Resources\CreditCollection;
use App\Http\Resources\CreditByClientCollection;
use App\Http\Resources\CreditResource;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
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

    public function indexByClient(Request $request)
    {
        $orgId = Auth::user()->organization_id;
        $sort = $request->query('sort_by', 'created_at');
        $storeId = $request->query('store_id');
        $clientId = $request->query('client_id');
        $order = $request->query('order', 'asc');
        $creditStatus = $request->query('credit_status', 'all');

        // Base query
        $query = Credit::where('organization_id', $orgId);

        if ($storeId) {
            $query->where('store_id', $storeId);
        }

        if ($clientId) {
            $query->where('client_id', $clientId);
        }

        if ($creditStatus !== 'all') {
            $query->where('credit_status', $creditStatus);
        }

        // Cargar relaciones necesarias
        $credits = $query->with('client')->get();

        if ($credits->isEmpty()) {
            return response()->json(['message' => 'No credits found for the specified filters.'], Response::HTTP_NOT_FOUND);
        }

        // Agrupar por cliente
        $groupedCredits = $credits->groupBy('client_id')->map(function ($clientCredits) {
            $firstCredit = $clientCredits->first();
            return (object) [
                'client_id'     => $firstCredit->client->id,
                'client_name'   => $firstCredit->client->name,
                'invoices_qty'  => $clientCredits->count(),
                'total_debt'    => $clientCredits->where('credit_status', '!=', 'paid')->sum('debt'),
                'total_paid'    => $clientCredits->where('credit_status', 'paid')->count(),
                'total_unpaid'  => $clientCredits->where('credit_status', '!=', 'paid')->count(),
                'created_at'    => $firstCredit->created_at,
                'updated_at'    => $firstCredit->updated_at,
            ];
        })->values();

        // Ordenar
        $groupedCredits = $groupedCredits->sortBy($sort, SORT_REGULAR, $order === 'desc')->values();

        return new CreditByClientCollection($groupedCredits);
    }

    public function indexByClientID($client_id)
    {
        $orgId = Auth::user()->organization_id;
        $show = request()->query('show', 'active');
        $per_page = request()->query('per_page', 20);
        $sort = request()->query('sort', 'created_at');
        $order = request()->query('order', 'asc');
    
        $credits = Credit::whereHas('client', function ($query) use ($orgId, $client_id) {
                $query->where('organization_id', $orgId)
                      ->where('id', $client_id);
            })
            ->when($show, function ($query) use ($show) {
                if ($show === 'all') {
                    return $query;
                } elseif ($show === 'active') {
                    return $query->where('credit_status', '!=', 'paid');
                } elseif ($show === 'paid') {
                    return $query->where('credit_status', 'paid');
                }
            })
            ->orderBy($sort, $order) // Orden dinámico
            ->paginate($per_page);   // Paginación
    
        if ($credits->isEmpty()) {
            return response()->json(['message' => 'No credits found for the specified organization.'], Response::HTTP_NOT_FOUND);
        }
    
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
    public function payment(Request $request)
    {
        $orgID = Auth::user()->organization_id;
        $credits =json_decode( $request->credits_id);

        if (!$request->amount || !$credits) {
            return response()->json(['message' => 'Amount and credits_id are required.'], 400);
        }

        if ($request->amoun > 0) {
            return response()->json(['message' => 'The amount exceeds the total debt of the selected credits.'], 400);
        }
        if (count($credits) == 0) {
            return response()->json(['message' => 'No credits selected.'], 400);
        }

        $amount = $request->amount; 
        $notes = $request->notes;

        

        foreach ($credits as $creditId) {
            $credit = Credit::where('id', $creditId)->where('organization_id', $orgID)->first(); 
    
            if ($credit) {
                if ($amount >= $credit->debt) {
                  
                    $amount -= $credit->debt; 
                    $credit->debt = 0; 
                    $credit->credit_status = 'paid'; 
                } else {
                    $credit->debt -= $amount; 
                    $amount = 0; 
                }
    
                $credit->save(); 
    
                $creditDetail = new CreditDetail();
                $creditDetail->credit_id = $credit->id;
                $creditDetail->amount = $request->amount;
                $creditDetail->date = date('Y-m-d');
                $creditDetail->note = $notes;
                $creditDetail->seller_id = $request->seller_id;
                $creditDetail->save();
    
                if ($amount == 0) {
                    break;
                }
            }else{
                return response()->json(['message' => 'Credit not found or does not belong to the organization.'], 404);
            }
        }

        $allCredistsList = [];
        foreach ($credits as $creditId) {
            $credit = Credit::where('id', $creditId)->where('organization_id', $orgID)->first();
            if ($credit) {
                $allCredistsList[] =new  CreditResource($credit);
            }
        }
     

        return response()->json( $allCredistsList, 200);
    }

}
