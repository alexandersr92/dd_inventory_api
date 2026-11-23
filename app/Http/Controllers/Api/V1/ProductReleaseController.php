<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductReleaseResource;
use App\Models\ProductRelease;
use Illuminate\Http\Request;

class ProductReleaseController extends Controller
{
    /**
     * Display a listing of published product releases.
     */
    public function index(Request $request)
    {
        $perPage = min((int) $request->input('per_page', 10), 50);

        $releases = ProductRelease::published()
            ->orderByDesc('published_at')
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return ProductReleaseResource::collection($releases);
    }
}
