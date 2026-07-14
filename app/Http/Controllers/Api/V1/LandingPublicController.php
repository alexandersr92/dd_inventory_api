<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\LandingContent;
use App\Models\LandingPlan;

class LandingPublicController extends Controller
{
    public function getPublicContent()
    {
        $contents = LandingContent::all()->pluck('content', 'section_key')->toArray();
        return response()->json($contents);
    }

    public function getPublicPlans()
    {
        $plans = LandingPlan::where('status', 'active')->orderBy('price', 'asc')->get();
        return response()->json($plans);
    }
}
