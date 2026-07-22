<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Plan;
use App\Services\PlanLimits;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class PlanController extends Controller
{
    /** Planes disponibles para contratar/renovar. */
    public function index()
    {
        return response()->json([
            'data' => Plan::where('is_active', true)->orderBy('price')->get(),
        ]);
    }

    /** Plan actual de la organización, con su uso vs límites. */
    public function current()
    {
        $organization = Organization::with('plan')->find(Auth::user()->organization_id);

        if (!$organization) {
            return response()->json(['message' => 'Organización no encontrada.'], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'plan' => $organization->plan,
            'is_lifetime' => (bool) $organization->is_lifetime,
            'license_expires_at' => $organization->license_expires_at,
            'usage' => PlanLimits::for($organization)->usage(),
        ]);
    }
}
