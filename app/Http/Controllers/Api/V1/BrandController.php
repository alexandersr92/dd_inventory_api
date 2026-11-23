<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Http\Requests\StoreBrandRequest;
use App\Http\Requests\UpdateBrandRequest;
use App\Http\Resources\BrandCollection;
use App\Http\Resources\BrandResource;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class BrandController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request)
    {
        $this->authorize('viewAny', Brand::class);
        $orgId = Auth::user()->organization_id;

        $query = Brand::where('organization_id', $orgId)
            ->withCount('products');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where('name', 'like', "%{$search}%");
        }

        if ($request->boolean('all')) {
            $brands = $query->orderBy('name', 'asc')->get();
            return BrandResource::collection($brands);
        }

        $perPage = (int) $request->input('per_page', 25);
        $brands = $query->orderBy('name', 'asc')->paginate($perPage);

        return BrandResource::collection($brands);
    }

    public function store(StoreBrandRequest $request)
    {
        $this->authorize('create', Brand::class);
        $orgId = Auth::user()->organization_id;

        $name = trim($request->name);

        $exists = Brand::where('organization_id', $orgId)
            ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower($name)])
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'Ya existe una marca con este nombre en la organización.'
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $brand = new Brand();
        $brand->organization_id = $orgId;
        $brand->name = $name;
        $brand->slug = Str::slug($name);
        $brand->status = $request->input('status', 'active');
        $brand->save();

        return (new BrandResource($brand))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Brand $brand)
    {
        $this->authorize('view', Brand::class);
        $brand->loadCount('products');
        return new BrandResource($brand);
    }

    public function update(UpdateBrandRequest $request, Brand $brand)
    {
        $this->authorize('update', Brand::class);
        $orgId = Auth::user()->organization_id;

        if ($request->has('name')) {
            $name = trim($request->name);
            $exists = Brand::where('organization_id', $orgId)
                ->where('id', '!=', $brand->id)
                ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower($name)])
                ->exists();

            if ($exists) {
                return response()->json([
                    'message' => 'Ya existe otra marca con este nombre.'
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $brand->name = $name;
            $brand->slug = Str::slug($name);
        }

        if ($request->has('status')) {
            $brand->status = $request->status;
        }

        $brand->save();

        return (new BrandResource($brand))
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }

    public function destroy(Brand $brand)
    {
        $this->authorize('delete', Brand::class);

        if ($brand->products()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar la marca porque tiene productos vinculados. Desactive la marca o reasigne sus productos.'
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $brand->delete();

        return response(null, 204);
    }
}
