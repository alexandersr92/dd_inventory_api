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
        $search = $request->query('search');

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

        if ($search) {
            $query->whereHas('client', function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%');
            });
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
        $show = request()->query('show', 'all');
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
        $creditsIds = is_array($request->credits_id) ? $request->credits_id : json_decode($request->credits_id);

        if (!$request->amount || !$creditsIds) {
            return response()->json(['message' => 'Amount and credits_id are required.'], 400);
        }

        if ($request->amount <= 0) {
            return response()->json(['message' => 'The amount must be greater than 0.'], 400);
        }

        if (count($creditsIds) == 0) {
            return response()->json(['message' => 'No credits selected.'], 400);
        }

        $remainingAmount = $request->amount; 
        $notes = $request->notes;

        foreach ($creditsIds as $creditId) {
            if ($remainingAmount <= 0) break;

            $credit = Credit::where('id', $creditId)->where('organization_id', $orgID)->first(); 
    
            if ($credit) {
                $appliedToThisCredit = 0;

                if ($remainingAmount >= $credit->debt) {
                    $appliedToThisCredit = $credit->debt;
                    $remainingAmount -= $credit->debt; 
                    $credit->debt = 0; 
                    $credit->credit_status = 'paid'; 
                } else {
                    $appliedToThisCredit = $remainingAmount;
                    $credit->debt -= $remainingAmount; 
                    $remainingAmount = 0; 
                }
    
                $credit->save(); 
    
                // Solo crear el detalle si realmente se aplicó un pago
                if ($appliedToThisCredit > 0) {
                    $creditDetail = new CreditDetail();
                    $creditDetail->credit_id = $credit->id;
                    $creditDetail->amount = $appliedToThisCredit; // Monto real aplicado a ESTE crédito
                    $creditDetail->date = date('Y-m-d');
                    $creditDetail->note = $notes;
                    $creditDetail->seller_id = $request->seller_id;
                    $creditDetail->save();
                }
            } else {
                return response()->json(['message' => "Credit $creditId not found or does not belong to the organization."], 404);
            }
        }

        $updatedCredits = [];
        foreach ($creditsIds as $creditId) {
            $credit = Credit::where('id', $creditId)->where('organization_id', $orgID)->first();
            if ($credit) {
                $updatedCredits[] = new CreditResource($credit);
            }
        }
     
        return response()->json($updatedCredits, 200);
    }

}
